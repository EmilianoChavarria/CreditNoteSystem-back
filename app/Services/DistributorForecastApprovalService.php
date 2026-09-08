<?php

namespace App\Services;

use App\Mail\ForecastFinalApprovedSummaryMail;
use App\Mail\ForecastPendingApprovalMail;
use App\Mail\ForecastPendingApprovalSummaryMail;
use App\Mail\ForecastRejectedSummaryMail;
use App\Mail\ForecastRequestApprovedSummaryMail;
use App\Models\Distributor;
use App\Models\DistributorForecast;
use App\Models\DistributorForecastChangeRequest;
use App\Models\DistributorForecastChangeRequestHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DistributorForecastApprovalService
{
    public function __construct(
        private readonly ForecastRoleService $roleService,
        private readonly NotificationService $notificationService,
        private readonly EmailSenderService $emailSender,
        private readonly DistributorForecastService $distributorForecastService,
    ) {
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

    // -------------------------------------------------------------------------
    // Submit
    // -------------------------------------------------------------------------

    public function submit(User $actor, int $distributorId, int $year, int $month, int $forecast): array
    {
        $distributor = Distributor::find($distributorId);

        if (!$distributor) {
            return ['success' => false, 'code' => 404, 'message' => 'Distribuidor no encontrado'];
        }

        $hasPending = DistributorForecastChangeRequest::where('distributorId', $distributorId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return ['success' => false, 'code' => 422, 'message' => 'Ya existe una solicitud pendiente para este mes'];
        }

        $previousForecast = DistributorForecast::where('distributorId', $distributorId)
            ->where('year', $year)
            ->where('month', $month)
            ->value('forecast') ?? 0;

        // FORECAST ADMIN y SALES ENGINEER MANAGER: aprobación directa. El SALES
        // MANAGER es el último paso del flujo, así que sus propios cambios no
        // tienen a quién escalar.
        if ($this->roleService->isForecastAdmin($actor) || $this->roleService->isSalesEngineerManager($actor)) {
            $changeRequest = null;

            DB::transaction(function () use ($actor, $distributorId, $year, $month, $forecast, $previousForecast, &$changeRequest): void {
                $changeRequest = DistributorForecastChangeRequest::create([
                    'distributorId'     => $distributorId,
                    'year'              => $year,
                    'month'             => $month,
                    'previousForecast'  => $previousForecast,
                    'proposedForecast'  => $forecast,
                    'status'            => 'approved',
                    'currentStep'       => 'auto_approved',
                    'approverUserId'    => $actor->id,
                    'submittedByUserId' => $actor->id,
                ]);

                DistributorForecastChangeRequestHistory::create([
                    'distributorForecastChangeRequestId' => $changeRequest->id,
                    'action'                             => 'auto_approved',
                    'actorUserId'                         => $actor->id,
                    'forecast'                            => $forecast,
                    'step'                                => 'auto_approved',
                ]);

                $this->distributorForecastService->upsertMonth($distributorId, $year, $month, $forecast, null);
            });

            $this->notifyClientApproved($distributor, (string) $distributor->businessName, [$changeRequest]);

            return ['success' => true, 'changeRequest' => $changeRequest->load('history.actor', 'submittedBy', 'approver')];
        }

        // SALES ENGINEER: un solo paso de aprobación, su SALES MANAGER.
        $step     = 'sales_manager';
        $approver = $distributor->salesManagerId
            ? User::where('isActive', true)->whereNull('deletedAt')->find((int) $distributor->salesManagerId)
            : null;

        if (!$approver) {
            return ['success' => false, 'code' => 422, 'message' => 'No se encontró un SALES ENGINEER / MANAGER disponible para este distribuidor'];
        }

        $changeRequest = null;

        DB::transaction(function () use ($actor, $distributorId, $year, $month, $forecast, $previousForecast, $step, $approver, &$changeRequest): void {
            $changeRequest = DistributorForecastChangeRequest::create([
                'distributorId'     => $distributorId,
                'year'              => $year,
                'month'             => $month,
                'previousForecast'  => $previousForecast,
                'proposedForecast'  => $forecast,
                'status'            => 'pending',
                'currentStep'       => $step,
                'approverUserId'    => $approver->id,
                'submittedByUserId' => $actor->id,
            ]);

            DistributorForecastChangeRequestHistory::create([
                'distributorForecastChangeRequestId' => $changeRequest->id,
                'action'                              => 'submitted',
                'actorUserId'                          => $actor->id,
                'forecast'                             => $forecast,
                'step'                                 => $step,
            ]);
        });

        $forecastAdmin = $this->roleService->findForecastAdmin();

        $this->notificationService->notifyDistributorForecastPendingApproval($changeRequest, $distributor->businessName);

        $this->sendEmail(new ForecastPendingApprovalMail(
            approverName:   (string) $approver->fullName,
            submitterName:  (string) $actor->fullName,
            clientId:       $distributor->id,
            clientName:     $distributor->businessName,
            month:          $month,
            year:           $year,
            proposedAmount: (string) $forecast,
            previousAmount: (string) $previousForecast,
        ), (string) $approver->email, cc: array_filter([(string) ($forecastAdmin?->email ?? '')]));

        return ['success' => true, 'changeRequest' => $changeRequest->load('history.actor', 'submittedBy', 'approver')];
    }

    // -------------------------------------------------------------------------
    // Submit en lote
    // -------------------------------------------------------------------------

    /**
     * Registra varios cambios de forecast en una sola operación. Cada mes es una
     * solicitud independiente, pero se resuelven en conjunto por distribuidor y
     * el correo al aprobador se envía UNA sola vez por distribuidor, con el
     * resumen de todos los meses incluidos.
     *
     * @param  array<int, array{distributorId:int, year:int, month:int, forecast:int}> $items
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
            $grouped[(int) $item['distributorId']][] = $item;
        }

        $created = [];
        $errors  = [];

        foreach ($grouped as $distributorId => $rows) {
            $distributorId = (int) $distributorId;
            $distributor   = Distributor::find($distributorId);

            if (!$distributor) {
                foreach ($rows as $row) {
                    $errors[] = [
                        'distributorId' => $distributorId,
                        'clientName'    => null,
                        'year'          => (int) $row['year'],
                        'month'         => (int) $row['month'],
                        'message'       => 'Distribuidor no encontrado',
                    ];
                }

                continue;
            }

            $step     = null;
            $approver = null;

            if (!$isAutoApproved) {
                $step     = 'sales_manager';
                $approver = $distributor->salesManagerId
                    ? User::where('isActive', true)->whereNull('deletedAt')->find((int) $distributor->salesManagerId)
                    : null;

                if (!$approver) {
                    foreach ($rows as $row) {
                        $errors[] = [
                            'distributorId' => $distributorId,
                            'clientName'    => $distributor->businessName,
                            'year'          => (int) $row['year'],
                            'month'         => (int) $row['month'],
                            'message'       => 'No se encontró un SALES ENGINEER / MANAGER disponible para este distribuidor',
                        ];
                    }

                    continue;
                }
            }

            $changesForEmail = [];
            $autoApproved    = [];

            foreach ($rows as $row) {
                $year     = (int) $row['year'];
                $month    = (int) $row['month'];
                $forecast = (int) $row['forecast'];

                $hasPending = DistributorForecastChangeRequest::where('distributorId', $distributorId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPending) {
                    $errors[] = [
                        'distributorId' => $distributorId,
                        'clientName'    => $distributor->businessName,
                        'year'          => $year,
                        'month'         => $month,
                        'message'       => 'Ya existe una solicitud pendiente para este mes',
                    ];

                    continue;
                }

                $previousForecast = (float) (DistributorForecast::where('distributorId', $distributorId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->value('forecast') ?? 0);

                $changeRequest = null;

                DB::transaction(function () use ($actor, $distributorId, $year, $month, $forecast, $previousForecast, $isAutoApproved, $step, $approver, &$changeRequest): void {
                    $changeRequest = DistributorForecastChangeRequest::create([
                        'distributorId'     => $distributorId,
                        'year'              => $year,
                        'month'             => $month,
                        'previousForecast'  => $previousForecast,
                        'proposedForecast'  => $forecast,
                        'status'            => $isAutoApproved ? 'approved' : 'pending',
                        'currentStep'       => $isAutoApproved ? 'auto_approved' : $step,
                        'approverUserId'    => $isAutoApproved ? $actor->id : $approver->id,
                        'submittedByUserId' => $actor->id,
                    ]);

                    DistributorForecastChangeRequestHistory::create([
                        'distributorForecastChangeRequestId' => $changeRequest->id,
                        'action'                             => $isAutoApproved ? 'auto_approved' : 'submitted',
                        'actorUserId'                        => $actor->id,
                        'forecast'                           => $forecast,
                        'step'                               => $isAutoApproved ? 'auto_approved' : $step,
                    ]);

                    if ($isAutoApproved) {
                        $this->distributorForecastService->upsertMonth($distributorId, $year, $month, $forecast, null);
                    }
                });

                $created[] = $this->formatRequest($changeRequest->load('history.actor', 'submittedBy', 'approver', 'distributor'));

                if ($isAutoApproved) {
                    $autoApproved[] = $changeRequest;
                }

                if (!$isAutoApproved) {
                    // La notificación in-app se mantiene individual por solicitud.
                    $this->notificationService->notifyDistributorForecastPendingApproval($changeRequest, $distributor->businessName);

                    $changesForEmail[] = [
                        'month'          => $month,
                        'monthLabel'     => ForecastApprovalService::monthLabel($month, $year),
                        'previousAmount' => $previousForecast,
                        'proposedAmount' => (float) $forecast,
                    ];
                }
            }

            // Aprobación directa: el distribuidor se entera de una vez, con todos sus meses.
            if ($isAutoApproved && !empty($autoApproved)) {
                $this->notifyClientApproved($distributor, (string) $distributor->businessName, $autoApproved);
            }

            // Un solo correo por distribuidor con el resumen de todos sus meses.
            if (!$isAutoApproved && !empty($changesForEmail)) {
                usort($changesForEmail, fn($a, $b) => $a['month'] <=> $b['month']);

                $this->sendEmail(new ForecastPendingApprovalSummaryMail(
                    approverName:  (string) $approver->fullName,
                    submitterName: (string) $actor->fullName,
                    clientId:      (int) $distributor->id,
                    clientName:    (string) $distributor->businessName,
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

    // -------------------------------------------------------------------------
    // Approve / Reject (siempre en conjunto por distribuidor)
    // -------------------------------------------------------------------------

    /**
     * Aprobar una solicitud aprueba TODAS las pendientes del mismo distribuidor:
     * la decision es todo o nada para que el aviso al SALES ENGINEER salga en un
     * unico correo por distribuidor con todos sus meses.
     */
    public function approve(User $actor, int $requestId): array
    {
        $changeRequest = DistributorForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        return $this->resolveDistributorGroup($actor, (int) $changeRequest->distributorId, true);
    }

    /** @see self::approve() */
    public function reject(User $actor, int $requestId): array
    {
        $changeRequest = DistributorForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        return $this->resolveDistributorGroup($actor, (int) $changeRequest->distributorId, false);
    }

    public function approveDistributorGroup(User $actor, int $distributorId): array
    {
        return $this->resolveDistributorGroup($actor, $distributorId, true);
    }

    public function rejectDistributorGroup(User $actor, int $distributorId): array
    {
        return $this->resolveDistributorGroup($actor, $distributorId, false);
    }

    /**
     * Resuelve en bloque todas las solicitudes pendientes de un distribuidor.
     *
     * @return array{success:bool, code?:int, message:string, resolved?:int}
     */
    private function resolveDistributorGroup(User $actor, int $distributorId, bool $approved): array
    {
        $requests = DistributorForecastChangeRequest::where('distributorId', $distributorId)
            ->where('status', 'pending')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        if ($requests->isEmpty()) {
            return ['success' => false, 'code' => 404, 'message' => 'No hay solicitudes pendientes para este distribuidor'];
        }

        foreach ($requests as $request) {
            if (!$this->canActOnRequest($actor, $request)) {
                return ['success' => false, 'code' => 403, 'message' => 'No eres el aprobador designado para todas las solicitudes de este distribuidor'];
            }
        }

        $distributor     = Distributor::find($distributorId);
        $distributorName = (string) ($distributor?->businessName ?? '');
        $forecastAdmin   = $this->roleService->findForecastAdmin();

        DB::transaction(function () use ($actor, $requests, $approved): void {
            foreach ($requests as $request) {
                DistributorForecastChangeRequestHistory::create([
                    'distributorForecastChangeRequestId' => $request->id,
                    'action'                             => $approved ? 'approved' : 'rejected',
                    'actorUserId'                        => $actor->id,
                    'forecast'                           => $request->proposedForecast,
                    'step'                               => (string) $request->currentStep,
                ]);

                $request->update(['status' => $approved ? 'approved' : 'rejected']);

                if ($approved) {
                    $this->distributorForecastService->upsertMonth(
                        (int) $request->distributorId,
                        (int) $request->year,
                        (int) $request->month,
                        (int) $request->proposedForecast,
                        null
                    );
                }
            }
        });

        // Aviso al distribuidor: un solo correo con todos los meses aprobados.
        if ($approved) {
            $this->notifyClientApproved($distributor, $distributorName, $requests->all());
        }

        // Un correo (y una notificacion in-app) por distribuidor para cada
        // creador, con todos los meses resueltos.
        foreach ($requests->groupBy('submittedByUserId') as $submitterId => $rows) {
            $submitter = User::find((int) $submitterId);

            $changes = $rows->map(fn(DistributorForecastChangeRequest $r) => [
                'month'          => (int) $r->month,
                'monthLabel'     => ForecastApprovalService::monthLabel((int) $r->month, (int) $r->year),
                'previousAmount' => (float) $r->previousForecast,
                'proposedAmount' => (float) $r->proposedForecast,
            ])->values()->all();

            $this->notificationService->notifyForecastGroupResolved(
                submitterUserId: (int) $submitterId,
                actor: $actor,
                clientId: $distributorId,
                clientName: $distributorName,
                monthLabels: array_column($changes, 'monthLabel'),
                approved: $approved,
                relatedId: (int) $rows->first()->id,
                isDistributor: true,
            );

            $mailable = $approved
                ? new ForecastRequestApprovedSummaryMail(
                    submitterName: (string) ($submitter?->fullName ?? ''),
                    approverName:  (string) $actor->fullName,
                    clientId:      $distributorId,
                    clientName:    $distributorName,
                    year:          (int) $rows->first()->year,
                    changes:       $changes,
                )
                : new ForecastRejectedSummaryMail(
                    submitterName: (string) ($submitter?->fullName ?? ''),
                    rejectorName:  (string) $actor->fullName,
                    clientId:      $distributorId,
                    clientName:    $distributorName,
                    year:          (int) $rows->first()->year,
                    changes:       $changes,
                );

            $this->sendEmail($mailable, (string) ($submitter?->email ?? ''), cc: array_filter([(string) ($forecastAdmin?->email ?? '')]));
        }

        $count = $requests->count();

        return [
            'success'  => true,
            'resolved' => $count,
            'message'  => $approved
                ? ($count === 1 ? 'Objetivo aprobado y aplicado al forecast del distribuidor' : "{$count} meses aprobados y aplicados al forecast del distribuidor")
                : ($count === 1 ? 'Solicitud rechazada' : "{$count} solicitudes rechazadas"),
        ];
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function getPendingForApprover(User $actor): Collection
    {
        return DistributorForecastChangeRequest::where('status', 'pending')
            // FORECAST ADMIN supervisa el flujo completo: ve todas las pendientes,
            // no solo las que tiene asignadas como aprobador.
            ->unless($this->roleService->isForecastAdmin($actor), fn($q) => $q->where('approverUserId', $actor->id))
            ->with([
                'submittedBy:id,fullName',
                'approver:id,fullName',
                'distributor:id,businessName',
                'history.actor:id,fullName',
            ])
            ->orderBy('createdAt')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));
    }

    public function getPendingBySubmitter(User $actor): Collection
    {
        return DistributorForecastChangeRequest::where('submittedByUserId', $actor->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->with([
                'approver:id,fullName',
                'distributor:id,businessName',
                'history.actor:id,fullName',
            ])
            ->orderByDesc('createdAt')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));
    }

    public function getMonthHistory(int $distributorId, int $year, int $month): Collection
    {
        return DistributorForecastChangeRequest::where('distributorId', $distributorId)
            ->where('year', $year)
            ->where('month', $month)
            ->with([
                'submittedBy:id,fullName',
                'approver:id,fullName',
                'distributor:id,businessName',
                'history.actor:id,fullName',
            ])
            ->orderBy('createdAt')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * El aprobador designado siempre puede resolver su solicitud. FORECAST ADMIN
     * puede resolver cualquiera porque supervisa el flujo completo.
     */
    private function canActOnRequest(User $actor, DistributorForecastChangeRequest $changeRequest): bool
    {
        return (int) $changeRequest->approverUserId === (int) $actor->id
            || $this->roleService->isForecastAdmin($actor);
    }


    /**
     * Avisa al distribuidor los meses que acaban de quedar aprobados: un solo
     * correo con todos sus meses, con copia oculta a su SALES MANAGER y al
     * FORECAST ADMIN.
     *
     * @param array<int, DistributorForecastChangeRequest> $requests
     */
    private function notifyClientApproved(?Distributor $distributor, string $distributorName, array $requests): void
    {
        if (empty($requests)) {
            return;
        }

        $changes = array_map(fn(DistributorForecastChangeRequest $r) => [
            'monthLabel'     => ForecastApprovalService::monthLabel((int) $r->month, (int) $r->year),
            'previousAmount' => (float) $r->previousForecast,
            'proposedAmount' => (float) $r->proposedForecast,
        ], $requests);

        $emails = $distributor?->emails
            ? array_values(array_filter(array_map('trim', explode(',', (string) $distributor->emails))))
            : [];

        $forecastAdmin = $this->roleService->findForecastAdmin();

        if (empty($emails)) {
            Log::warning('Forecast aprobado sin correos de distribuidor', [
                'distributorId' => $distributor?->id,
                'distributor'   => $distributorName,
                'months'        => count($changes),
            ]);
        } else {
            $this->sendEmail(
                new ForecastFinalApprovedSummaryMail(clientName: $distributorName, changes: $changes),
                $emails,
                bcc: array_values(array_filter([
                    (string) ($distributor?->salesManager?->email ?? ''),
                    (string) ($forecastAdmin?->email ?? ''),
                ])),
            );
        }

        $now = now();

        foreach ($requests as $request) {
            $request->forceFill(['clientNotifiedAt' => $now])->save();
        }
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
            Log::error('Error enviando correo de forecast de distribuidor', [
                'mail'  => get_class($mailable),
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatRequest(DistributorForecastChangeRequest $r): array
    {
        return [
            'id'               => $r->id,
            'distributorId'    => $r->distributorId,
            'clientName'       => $r->distributor?->businessName ?? null,
            'year'             => $r->year,
            'month'            => $r->month,
            'previousForecast' => $r->previousForecast,
            'proposedForecast' => $r->proposedForecast,
            'status'           => $r->status,
            'currentStep'      => $r->currentStep,
            'distributor'      => $r->distributor ? ['id' => $r->distributor->id, 'businessName' => $r->distributor->businessName] : null,
            'submittedBy'      => $r->submittedBy ? ['id' => $r->submittedBy->id, 'fullName' => $r->submittedBy->fullName] : null,
            'approver'         => $r->approver    ? ['id' => $r->approver->id,    'fullName' => $r->approver->fullName]    : null,
            'history'          => $r->history->map(fn($h) => [
                'action'   => $h->action,
                'step'     => $h->step,
                'forecast' => $h->forecast,
                'actor'    => $h->actor ? ['id' => $h->actor->id, 'fullName' => $h->actor->fullName] : null,
                'at'       => $h->createdAt,
            ])->values(),
            'submittedAt' => $r->createdAt,
        ];
    }
}
