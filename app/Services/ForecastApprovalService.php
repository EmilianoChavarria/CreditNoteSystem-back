<?php

namespace App\Services;

use App\Mail\ForecastFinalApprovedMail;
use App\Mail\ForecastPendingApprovalMail;
use App\Mail\ForecastRejectedMail;
use App\Mail\ForecastRequestApprovedMail;
use App\Models\Distributor;
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

        // FORECAST ADMIN: aprobación directa, sin flujo de aprobación
        if ($this->roleService->isForecastAdmin($actor)) {
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

        $isSalesManager = $this->roleService->isSalesEngineerManager($actor);

        if ($isSalesManager) {
            $step     = 'general_manager';
            $approver = $this->roleService->findGeneralManager();
        } else {
            $step     = 'sales_manager';
            $approver = $this->findSalesManagerForClient($idClient);
        }

        if (!$approver) {
            $label = $isSalesManager ? 'GENERAL MANAGER' : 'SALES ENGINEER / MANAGER';
            return ['success' => false, 'code' => 422, 'message' => "No se encontró un {$label} disponible para este cliente"];
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
    // Approve
    // -------------------------------------------------------------------------

    public function approve(User $actor, int $requestId): array
    {
        $changeRequest = ForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        if ((int) $changeRequest->approverUserId !== (int) $actor->id) {
            return ['success' => false, 'code' => 403, 'message' => 'No eres el aprobador designado para esta solicitud'];
        }

        $clientName    = $this->getClientName((int) $changeRequest->idClient);
        $forecastAdmin = $this->roleService->findForecastAdmin();
        $submitter     = User::find((int) $changeRequest->submittedByUserId);

        if ($changeRequest->currentStep === 'sales_manager') {
            $generalManager = $this->roleService->findGeneralManager();

            if (!$generalManager) {
                return ['success' => false, 'code' => 422, 'message' => 'No se encontró un GENERAL MANAGER disponible'];
            }

            DB::transaction(function () use ($actor, $changeRequest, $generalManager): void {
                ForecastChangeRequestHistory::create([
                    'forecastChangeRequestId' => $changeRequest->id,
                    'action'                  => 'approved',
                    'actorUserId'             => $actor->id,
                    'amount'                  => $changeRequest->proposedAmount,
                    'step'                    => 'sales_manager',
                ]);

                $changeRequest->update([
                    'currentStep'    => 'general_manager',
                    'approverUserId' => $generalManager->id,
                ]);
            });

            $this->notificationService->notifyForecastPendingApproval($changeRequest, $clientName);
            $this->notificationService->notifyForecastStepApproved($changeRequest, $actor, $clientName);

            // Email al GM + CC submitter (SE) + FORECAST ADMIN
            $cc = array_filter([
                (string) ($submitter?->email ?? ''),
                (string) ($forecastAdmin?->email ?? ''),
            ]);

            $this->sendEmail(new ForecastPendingApprovalMail(
                approverName:   (string) $generalManager->fullName,
                submitterName:  (string) ($submitter?->fullName ?? ''),
                clientId:       (int) $changeRequest->idClient,
                clientName:     $clientName,
                month:          (int) $changeRequest->month,
                year:           (int) $changeRequest->year,
                proposedAmount: (string) $changeRequest->proposedAmount,
                previousAmount: (string) $changeRequest->previousAmount,
            ), (string) $generalManager->email, cc: $cc);

            return ['success' => true, 'message' => 'Aprobado por SALES MANAGER, pendiente de GENERAL MANAGER'];
        }

        // general_manager: aprobación final — actualiza ForecastSale
        DB::transaction(function () use ($actor, $changeRequest): void {
            ForecastChangeRequestHistory::create([
                'forecastChangeRequestId' => $changeRequest->id,
                'action'                  => 'approved',
                'actorUserId'             => $actor->id,
                'amount'                  => $changeRequest->proposedAmount,
                'step'                    => 'general_manager',
            ]);

            $changeRequest->update(['status' => 'approved']);

            ForecastSale::updateOrCreate(
                ['idClient' => (int) $changeRequest->idClient, 'year' => (int) $changeRequest->year, 'month' => (int) $changeRequest->month],
                ['amount'   => (float) $changeRequest->proposedAmount]
            );
        });

        $this->notificationService->notifyForecastApproved($changeRequest, $actor, $clientName);

        $salesManager = $this->findSalesManagerForClient((int) $changeRequest->idClient);
        $clientEmails = $this->getClientEmails((int) $changeRequest->idClient);

        // Email TO: correos del cliente (distribuidor) | BCC: SM + FORECAST ADMIN
        // (el submitter recibe su propio correo dedicado, ver ForecastRequestApprovedMail más abajo)
        $bcc = array_values(array_filter([
            (string) ($salesManager?->email ?? ''),
            (string) ($forecastAdmin?->email ?? ''),
        ]));

        $this->sendEmail(new ForecastFinalApprovedMail(
            submitterName:  (string) ($submitter?->fullName ?? ''),
            approverName:   (string) $actor->fullName,
            clientId:       (int) $changeRequest->idClient,
            clientName:     $clientName,
            month:          (int) $changeRequest->month,
            year:           (int) $changeRequest->year,
            proposedAmount: (string) $changeRequest->proposedAmount,
            previousAmount: (string) $changeRequest->previousAmount,
        ), $clientEmails, bcc: $bcc);

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

        if ((int) $changeRequest->approverUserId !== (int) $actor->id) {
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
        return ForecastChangeRequest::where('approverUserId', $actor->id)
            ->where('status', 'pending')
            ->with([
                'submittedBy:id,fullName',
                'history.actor:id,fullName',
            ])
            ->orderBy('createdAt')
            ->get()
            ->map(function ($r) {
                /** @var ForecastChangeRequest $r */
                return $this->formatRequest($r);
            });
    }

    public function getPendingBySubmitter(User $actor): Collection
    {
        return ForecastChangeRequest::where('submittedByUserId', $actor->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->with([
                'approver:id,fullName',
                'history.actor:id,fullName',
            ])
            ->orderByDesc('createdAt')
            ->get()
            ->map(function ($r) {
                /** @var ForecastChangeRequest $r */
                return $this->formatRequest($r);
            });
    }

    public function getMonthHistory(int $idClient, int $year, int $month): Collection
    {
        return ForecastChangeRequest::where('idClient', $idClient)
            ->where('year', $year)
            ->where('month', $month)
            ->with([
                'submittedBy:id,fullName',
                'approver:id,fullName',
                'history.actor:id,fullName',
            ])
            ->orderBy('createdAt')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));
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

    private function findSalesManagerForClient(int $idClient): ?User
    {
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

    private function getClientName(int $idClient): string
    {
        return (string) (DB::connection(self::EXT_CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('idCliente', $idClient)
            ->value('razonSocial') ?? '');
    }

    /** @return string[] */
    private function getClientEmails(int $idClient): array
    {
        $raw = Distributor::where('clientNumber', (string) $idClient)->value('emails');

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

    private function formatRequest(ForecastChangeRequest $r): array
    {
        return [
            'id'             => $r->id,
            'idClient'       => $r->idClient,
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
