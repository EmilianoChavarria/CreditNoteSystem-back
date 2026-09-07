<?php

namespace App\Services;

use App\Mail\ForecastPendingApprovalMail;
use App\Mail\ForecastPendingApprovalSummaryMail;
use App\Mail\ForecastRejectedMail;
use App\Mail\ForecastRequestApprovedMail;
use App\Models\ClientGroup;
use App\Models\ClientGroupMember;
use App\Models\Distributor;
use App\Models\NationalCustomer;
use App\Models\ForecastChangeRequest;
use App\Models\ForecastChangeRequestHistory;
use App\Models\ForecastSale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ForecastApprovalService
{
    private const EXT_CONNECTION   = 'invoices';
    private const CLIENT_TABLE     = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EmailSenderService  $emailSender,
        private readonly ForecastRoleService $roleService,
    ) {
    }

    // -------------------------------------------------------------------------
    // Submit
    // -------------------------------------------------------------------------

    public function submit(User $actor, int $idClient, int $year, int $month, float $amount): array
    {
        $hasPending = ForecastChangeRequest::where('idClient', $idClient)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return ['success' => false, 'code' => 422, 'message' => 'Ya existe una solicitud pendiente para este mes'];
        }

        $previousAmount = ForecastSale::where('idClient', $idClient)
            ->where('year', $year)
            ->where('month', $month)
            ->value('amount') ?? 0;

        $isSalesManager = $this->roleService->isSalesEngineerManager($actor);

        // FORECAST ADMIN y SALES ENGINEER MANAGER: aprobación directa. El SALES
        // MANAGER es el último paso del flujo, así que sus propios cambios no
        // tienen a quién escalar.
        if ($this->roleService->isForecastAdmin($actor) || $isSalesManager) {
            $changeRequest = null;

            DB::transaction(function () use ($actor, $idClient, $year, $month, $amount, $previousAmount, &$changeRequest): void {
                $changeRequest = ForecastChangeRequest::create([
                    'idClient'          => $idClient,
                    'year'              => $year,
                    'month'             => $month,
                    'previousAmount'    => $previousAmount,
                    'proposedAmount'    => $amount,
                    'status'            => 'approved',
                    'currentStep'       => 'auto_approved',
                    'approverUserId'    => $actor->id,
                    'submittedByUserId' => $actor->id,
                ]);

                ForecastChangeRequestHistory::create([
                    'forecastChangeRequestId' => $changeRequest->id,
                    'action'                  => 'auto_approved',
                    'actorUserId'             => $actor->id,
                    'amount'                  => $amount,
                    'step'                    => 'auto_approved',
                ]);

                ForecastSale::updateOrCreate(
                    ['idClient' => $idClient, 'year' => $year, 'month' => $month],
                    ['amount'   => $amount]
                );
            });

            return ['success' => true, 'changeRequest' => $changeRequest->load('history.actor', 'submittedBy', 'approver')];
        }

        // SALES ENGINEER: un solo paso de aprobación, su SALES MANAGER.
        $step     = 'sales_manager';
        $approver = $this->findSalesManagerForClient($idClient);

        if (!$approver) {
            return ['success' => false, 'code' => 422, 'message' => 'No se encontró un SALES ENGINEER / MANAGER disponible para este cliente'];
        }

        $changeRequest = null;

        DB::transaction(function () use ($actor, $idClient, $year, $month, $amount, $previousAmount, $step, $approver, &$changeRequest): void {
            $changeRequest = ForecastChangeRequest::create([
                'idClient'          => $idClient,
                'year'              => $year,
                'month'             => $month,
                'previousAmount'    => $previousAmount,
                'proposedAmount'    => $amount,
                'status'            => 'pending',
                'currentStep'       => $step,
                'approverUserId'    => $approver->id,
                'submittedByUserId' => $actor->id,
            ]);

            ForecastChangeRequestHistory::create([
                'forecastChangeRequestId' => $changeRequest->id,
                'action'                  => 'submitted',
                'actorUserId'             => $actor->id,
                'amount'                  => $amount,
                'step'                    => $step,
            ]);
        });

        $clientName   = $this->getClientName($idClient);
        $forecastAdmin = $this->roleService->findForecastAdmin();

        $this->notificationService->notifyForecastPendingApproval($changeRequest, $clientName);

        // Email al aprobador + CC FORECAST ADMIN
        $this->sendEmail(new ForecastPendingApprovalMail(
            approverName:   (string) $approver->fullName,
            submitterName:  (string) $actor->fullName,
            clientId:       $idClient,
            clientName:     $clientName,
            month:          $month,
            year:           $year,
            proposedAmount: (string) $amount,
            previousAmount: (string) $previousAmount,
        ), (string) $approver->email, cc: array_filter([(string) ($forecastAdmin?->email ?? '')]));

        return ['success' => true, 'changeRequest' => $changeRequest->load('history.actor', 'submittedBy', 'approver')];
    }

    // -------------------------------------------------------------------------
    // Submit en lote
    // -------------------------------------------------------------------------

    /**
     * Registra varios cambios de forecast en una sola operación. Cada mes sigue
     * siendo una solicitud independiente (se aprueba/rechaza por separado), pero
     * el correo al aprobador se envía UNA sola vez por cliente, con el resumen
     * de todos los meses incluidos.
     *
     * @param  array<int, array{idClient:int, year:int, month:int, amount:float}> $items
     * @return array{success:bool, code?:int, message?:string, created?:array, errors?:array}
     */
    public function submitBatch(User $actor, array $items): array
    {
        // El SALES MANAGER es el último paso del flujo: sus propios cambios se
        // aplican directo, igual que los del FORECAST ADMIN.
        $isAutoApproved = $this->roleService->isForecastAdmin($actor)
            || $this->roleService->isSalesEngineerManager($actor);
        $forecastAdmin  = $this->roleService->findForecastAdmin();

        $grouped = [];
        foreach ($items as $item) {
            $grouped[(int) $item['idClient']][] = $item;
        }

        $clientNames = $this->getClientNames(array_keys($grouped));

        $created = [];
        $errors  = [];

        foreach ($grouped as $idClient => $rows) {
            $idClient   = (int) $idClient;
            $clientName = $clientNames[$idClient] ?? $this->getClientName($idClient);

            $step     = null;
            $approver = null;

            if (!$isAutoApproved) {
                $step     = 'sales_manager';
                $approver = $this->findSalesManagerForClient($idClient);

                if (!$approver) {
                    foreach ($rows as $row) {
                        $errors[] = [
                            'idClient'   => $idClient,
                            'clientName' => $clientName,
                            'year'       => (int) $row['year'],
                            'month'      => (int) $row['month'],
                            'message'    => 'No se encontró un SALES ENGINEER / MANAGER disponible para este cliente',
                        ];
                    }

                    continue;
                }
            }

            $changesForEmail = [];

            foreach ($rows as $row) {
                $year   = (int) $row['year'];
                $month  = (int) $row['month'];
                $amount = (float) $row['amount'];

                $hasPending = ForecastChangeRequest::where('idClient', $idClient)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPending) {
                    $errors[] = [
                        'idClient'   => $idClient,
                        'clientName' => $clientName,
                        'year'       => $year,
                        'month'      => $month,
                        'message'    => 'Ya existe una solicitud pendiente para este mes',
                    ];

                    continue;
                }

                $previousAmount = (float) (ForecastSale::where('idClient', $idClient)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->value('amount') ?? 0);

                $changeRequest = null;

                DB::transaction(function () use ($actor, $idClient, $year, $month, $amount, $previousAmount, $isAutoApproved, $step, $approver, &$changeRequest): void {
                    $changeRequest = ForecastChangeRequest::create([
                        'idClient'          => $idClient,
                        'year'              => $year,
                        'month'             => $month,
                        'previousAmount'    => $previousAmount,
                        'proposedAmount'    => $amount,
                        'status'            => $isAutoApproved ? 'approved' : 'pending',
                        'currentStep'       => $isAutoApproved ? 'auto_approved' : $step,
                        'approverUserId'    => $isAutoApproved ? $actor->id : $approver->id,
                        'submittedByUserId' => $actor->id,
                    ]);

                    ForecastChangeRequestHistory::create([
                        'forecastChangeRequestId' => $changeRequest->id,
                        'action'                  => $isAutoApproved ? 'auto_approved' : 'submitted',
                        'actorUserId'             => $actor->id,
                        'amount'                  => $amount,
                        'step'                    => $isAutoApproved ? 'auto_approved' : $step,
                    ]);

                    if ($isAutoApproved) {
                        ForecastSale::updateOrCreate(
                            ['idClient' => $idClient, 'year' => $year, 'month' => $month],
                            ['amount'   => $amount]
                        );
                    }
                });

                $created[] = $this->formatRequest($changeRequest->load('history.actor', 'submittedBy', 'approver'), $clientNames);

                if (!$isAutoApproved) {
                    // La notificación in-app se mantiene individual por solicitud.
                    $this->notificationService->notifyForecastPendingApproval($changeRequest, $clientName);

                    $changesForEmail[] = [
                        'month'          => $month,
                        'monthLabel'     => self::monthLabel($month, $year),
                        'previousAmount' => $previousAmount,
                        'proposedAmount' => $amount,
                    ];
                }
            }

            // Un solo correo por cliente con el resumen de todos sus meses.
            if (!$isAutoApproved && !empty($changesForEmail)) {
                usort($changesForEmail, fn($a, $b) => $a['month'] <=> $b['month']);

                $this->sendEmail(new ForecastPendingApprovalSummaryMail(
                    approverName:  (string) $approver->fullName,
                    submitterName: (string) $actor->fullName,
                    clientId:      $idClient,
                    clientName:    $clientName,
                    year:          (int) $rows[0]['year'],
                    changes:       $changesForEmail,
                ), (string) $approver->email, cc: array_filter([(string) ($forecastAdmin?->email ?? '')]));
            }
        }

        if (empty($created) && !empty($errors)) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => $errors[0]['message'],
                'errors'  => $errors,
            ];
        }

        return ['success' => true, 'created' => $created, 'errors' => $errors];
    }

    public static function monthLabel(int $month, int $year): string
    {
        $names = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return ($names[$month - 1] ?? (string) $month) . " {$year}";
    }

    // -------------------------------------------------------------------------
    // Approve
    // -------------------------------------------------------------------------

    public function approve(User $actor, int $requestId): array
    {
        $changeRequest = ForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        if (!$this->canActOnRequest($actor, $changeRequest)) {
            return ['success' => false, 'code' => 403, 'message' => 'No eres el aprobador designado para esta solicitud'];
        }

        $clientName    = $this->getClientName((int) $changeRequest->idClient);
        $forecastAdmin = $this->roleService->findForecastAdmin();
        $submitter     = User::find((int) $changeRequest->submittedByUserId);

        // El SALES MANAGER es el único paso de aprobación: al aprobar, el cambio
        // queda aplicado sobre ForecastSale.
        DB::transaction(function () use ($actor, $changeRequest): void {
            ForecastChangeRequestHistory::create([
                'forecastChangeRequestId' => $changeRequest->id,
                'action'                  => 'approved',
                'actorUserId'             => $actor->id,
                'amount'                  => $changeRequest->proposedAmount,
                'step'                    => (string) $changeRequest->currentStep,
            ]);

            $changeRequest->update(['status' => 'approved']);

            ForecastSale::updateOrCreate(
                ['idClient' => (int) $changeRequest->idClient, 'year' => (int) $changeRequest->year, 'month' => (int) $changeRequest->month],
                ['amount'   => (float) $changeRequest->proposedAmount]
            );
        });

        $this->notificationService->notifyForecastApproved($changeRequest, $actor, $clientName);

        // El aviso al cliente no sale aquí: lo manda el scheduler diario
        // (forecast:notify-approved-clients) agrupando todos sus meses aprobados
        // en un solo correo.

        // Email dedicado al creador: "tu solicitud fue aprobada" (CC FORECAST ADMIN)
        $this->sendEmail(new ForecastRequestApprovedMail(
            submitterName:  (string) ($submitter?->fullName ?? ''),
            approverName:   (string) $actor->fullName,
            clientId:       (int) $changeRequest->idClient,
            clientName:     $clientName,
            month:          (int) $changeRequest->month,
            year:           (int) $changeRequest->year,
            proposedAmount: (string) $changeRequest->proposedAmount,
            previousAmount: (string) $changeRequest->previousAmount,
        ), (string) ($submitter?->email ?? ''), cc: array_filter([(string) ($forecastAdmin?->email ?? '')]));

        return ['success' => true, 'message' => 'Monto aprobado y aplicado al forecast'];
    }

    // -------------------------------------------------------------------------
    // Reject
    // -------------------------------------------------------------------------

    public function reject(User $actor, int $requestId): array
    {
        $changeRequest = ForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        if (!$this->canActOnRequest($actor, $changeRequest)) {
            return ['success' => false, 'code' => 403, 'message' => 'No eres el aprobador designado para esta solicitud'];
        }

        DB::transaction(function () use ($actor, $changeRequest): void {
            ForecastChangeRequestHistory::create([
                'forecastChangeRequestId' => $changeRequest->id,
                'action'                  => 'rejected',
                'actorUserId'             => $actor->id,
                'amount'                  => $changeRequest->proposedAmount,
                'step'                    => $changeRequest->currentStep,
            ]);

            $changeRequest->update(['status' => 'rejected']);
        });

        $clientName    = $this->getClientName((int) $changeRequest->idClient);
        $forecastAdmin = $this->roleService->findForecastAdmin();
        $submitter     = User::find((int) $changeRequest->submittedByUserId);

        $this->notificationService->notifyForecastRejected($changeRequest, $actor, $clientName);

        // Email al SE + CC FORECAST ADMIN
        $cc = array_filter([(string) ($forecastAdmin?->email ?? '')]);

        $this->sendEmail(new ForecastRejectedMail(
            submitterName:  (string) ($submitter?->fullName ?? ''),
            rejectorName:   (string) $actor->fullName,
            clientId:       (int) $changeRequest->idClient,
            clientName:     $clientName,
            month:          (int) $changeRequest->month,
            year:           (int) $changeRequest->year,
            proposedAmount: (string) $changeRequest->proposedAmount,
        ), (string) ($submitter?->email ?? ''), cc: $cc);

        return ['success' => true, 'message' => 'Solicitud rechazada'];
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function getPendingForApprover(User $actor): Collection
    {
        $requests = ForecastChangeRequest::where('status', 'pending')
            // FORECAST ADMIN supervisa el flujo completo: ve todas las pendientes,
            // no solo las que tiene asignadas como aprobador.
            ->unless($this->roleService->isForecastAdmin($actor), fn($q) => $q->where('approverUserId', $actor->id))
            ->with([
                'submittedBy:id,fullName',
                'approver:id,fullName',
                'history.actor:id,fullName',
            ])
            ->orderBy('createdAt')
            ->get();

        $clientNames = $this->getClientNames($requests->pluck('idClient')->unique()->all());

        return $requests->map(function ($r) use ($clientNames) {
            /** @var ForecastChangeRequest $r */
            return $this->formatRequest($r, $clientNames);
        });
    }

    public function getPendingBySubmitter(User $actor): Collection
    {
        $requests = ForecastChangeRequest::where('submittedByUserId', $actor->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->with([
                'approver:id,fullName',
                'history.actor:id,fullName',
            ])
            ->orderByDesc('createdAt')
            ->get();

        $clientNames = $this->getClientNames($requests->pluck('idClient')->unique()->all());

        return $requests->map(function ($r) use ($clientNames) {
            /** @var ForecastChangeRequest $r */
            return $this->formatRequest($r, $clientNames);
        });
    }

    public function getMonthHistory(int $idClient, int $year, int $month): Collection
    {
        $requests = ForecastChangeRequest::where('idClient', $idClient)
            ->where('year', $year)
            ->where('month', $month)
            ->with([
                'submittedBy:id,fullName',
                'approver:id,fullName',
                'history.actor:id,fullName',
            ])
            ->orderBy('createdAt')
            ->get();

        $clientNames = $this->getClientNames([$idClient]);

        return $requests->map(fn($r) => $this->formatRequest($r, $clientNames));
    }

    public function getPendingMapForClient(int $idClient, int $year): Collection
    {
        return ForecastChangeRequest::where('idClient', $idClient)
            ->where('year', $year)
            ->where('status', 'pending')
            ->get(['id', 'month', 'proposedAmount', 'currentStep', 'createdAt'])
            ->keyBy('month');
    }

    public function getPendingMapForClients(array $idClients, int $year): Collection
    {
        return ForecastChangeRequest::whereIn('idClient', $idClients)
            ->where('year', $year)
            ->where('status', 'pending')
            ->get(['id', 'idClient', 'month', 'proposedAmount', 'currentStep', 'createdAt'])
            ->groupBy('idClient')
            ->map(fn($rows) => $rows->keyBy('month'));
    }

    // -------------------------------------------------------------------------
    // Permission helpers
    // -------------------------------------------------------------------------

    public function canSubmitChange(User $user): bool
    {
        return $this->roleService->canSubmitChange($user);
    }

    public function canApprove(User $user): bool
    {
        return $this->roleService->canApprove($user);
    }

    public function isForecastAdmin(User $user): bool
    {
        return $this->roleService->isForecastAdmin($user);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * El aprobador designado siempre puede resolver su solicitud. FORECAST ADMIN
     * puede resolver cualquiera porque supervisa el flujo completo.
     */
    private function canActOnRequest(User $actor, ForecastChangeRequest $changeRequest): bool
    {
        return (int) $changeRequest->approverUserId === (int) $actor->id
            || $this->roleService->isForecastAdmin($actor);
    }

    private function findSalesManagerForClient(int $idClient): ?User
    {
        $group = ClientGroup::find($idClient);

        if ($group) {
            return $this->findSalesManagerForGroup($group);
        }

        $salesManagerId = DB::connection(self::EXT_CONNECTION)
            ->table(self::CLIENT_EXT_TABLE)
            ->where('idCliente', $idClient)
            ->value('salesManagerId');

        if (!$salesManagerId) {
            return null;
        }

        return User::where('isActive', true)
            ->whereNull('deletedAt')
            ->find((int) $salesManagerId);
    }

    private function findSalesManagerForGroup(ClientGroup $group): ?User
    {
        if (!$group->salesManagerId) {
            return null;
        }

        return User::where('isActive', true)
            ->whereNull('deletedAt')
            ->find((int) $group->salesManagerId);
    }

    private function getClientName(int $idClient): string
    {
        $group = ClientGroup::find($idClient);

        if ($group) {
            return (string) $group->name;
        }

        return (string) (DB::connection(self::EXT_CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('idCliente', $idClient)
            ->value('razonSocial') ?? '');
    }

    /**
     * @param  array<int, int> $idClients
     * @return array<int, string> idCliente => razonSocial (o groupId => nombre del grupo)
     */
    public function getClientNames(array $idClients): array
    {
        if (empty($idClients)) {
            return [];
        }

        $groupNames = ClientGroup::whereIn('id', $idClients)->pluck('name', 'id')->all();

        $remainingIds = array_values(array_diff($idClients, array_keys($groupNames)));

        $clientNames = empty($remainingIds) ? [] : DB::connection(self::EXT_CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $remainingIds)
            ->pluck('razonSocial', 'idCliente')
            ->all();

        return $groupNames + $clientNames;
    }

    /** @return string[] */
    public function getClientEmails(int $idClient): array
    {
        // Un grupo no tiene correos propios: se avisa a los de sus miembros.
        if (ClientGroup::where('id', $idClient)->exists()) {
            $memberIds = ClientGroupMember::where('groupId', $idClient)->pluck('clientId')->all();

            $emails = [];
            foreach ($memberIds as $memberId) {
                $emails = array_merge($emails, $this->getClientEmails((int) $memberId));
            }

            return array_values(array_unique($emails));
        }

        // Los clientes nacionales guardan sus correos en national_customers; los
        // extranjeros, en distributors.
        $raw = NationalCustomer::where('customerNumber', (string) $idClient)->value('emails')
            ?: Distributor::where('clientNumber', (string) $idClient)->value('emails');

        if (!$raw) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    /**
     * @param string|string[] $to
     * @param string[]        $cc
     * @param string[]        $bcc
     */
    private function sendEmail(\Illuminate\Mail\Mailable $mailable, string|array $to, array $cc = [], array $bcc = []): void
    {
        $isEmpty = is_array($to) ? empty(array_filter($to)) : $to === '';

        if ($isEmpty) {
            return;
        }

        try {
            $this->emailSender->sendWithCopies($mailable, $to, $cc, $bcc);
        } catch (Throwable $e) {
            Log::error('Error enviando correo de forecast', [
                'mail'  => get_class($mailable),
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<int, string> $clientNames idCliente => razonSocial */
    private function formatRequest(ForecastChangeRequest $r, array $clientNames = []): array
    {
        return [
            'id'             => $r->id,
            'idClient'       => $r->idClient,
            'clientName'     => $clientNames[$r->idClient] ?? null,
            'year'           => $r->year,
            'month'          => $r->month,
            'previousAmount' => $r->previousAmount,
            'proposedAmount' => $r->proposedAmount,
            'status'         => $r->status,
            'currentStep'    => $r->currentStep,
            'submittedBy'    => $r->submittedBy ? ['id' => $r->submittedBy->id, 'fullName' => $r->submittedBy->fullName] : null,
            'approver'       => $r->approver    ? ['id' => $r->approver->id,    'fullName' => $r->approver->fullName]    : null,
            'history'        => $r->history->map(fn($h) => [
                'action' => $h->action,
                'step'   => $h->step,
                'amount' => $h->amount,
                'actor'  => $h->actor ? ['id' => $h->actor->id, 'fullName' => $h->actor->fullName] : null,
                'at'     => $h->createdAt,
            ])->values(),
            'submittedAt' => $r->createdAt,
        ];
    }
}
