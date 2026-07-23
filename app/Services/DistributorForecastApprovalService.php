<?php

namespace App\Services;

use App\Mail\ForecastFinalApprovedMail;
use App\Mail\ForecastPendingApprovalMail;
use App\Mail\ForecastRejectedMail;
use App\Mail\ForecastRequestApprovedMail;
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

        // FORECAST ADMIN: aprobación directa, sin flujo de aprobación
        if ($this->roleService->isForecastAdmin($actor)) {
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

            return ['success' => true, 'changeRequest' => $changeRequest->load('history.actor', 'submittedBy', 'approver')];
        }

        $isSalesManager = $this->roleService->isSalesEngineerManager($actor);

        if ($isSalesManager) {
            $step     = 'general_manager';
            $approver = $this->roleService->findGeneralManager();
        } else {
            $step     = 'sales_manager';
            $approver = $distributor->salesManagerId ? User::where('isActive', true)->whereNull('deletedAt')->find((int) $distributor->salesManagerId) : null;
        }

        if (!$approver) {
            $label = $isSalesManager ? 'GENERAL MANAGER' : 'SALES ENGINEER / MANAGER';
            return ['success' => false, 'code' => 422, 'message' => "No se encontró un {$label} disponible para este distribuidor"];
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
    // Approve
    // -------------------------------------------------------------------------

    public function approve(User $actor, int $requestId): array
    {
        $changeRequest = DistributorForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        if ((int) $changeRequest->approverUserId !== (int) $actor->id) {
            return ['success' => false, 'code' => 403, 'message' => 'No eres el aprobador designado para esta solicitud'];
        }

        $distributor   = Distributor::find((int) $changeRequest->distributorId);
        $forecastAdmin = $this->roleService->findForecastAdmin();
        $submitter     = User::find((int) $changeRequest->submittedByUserId);

        if ($changeRequest->currentStep === 'sales_manager') {
            $generalManager = $this->roleService->findGeneralManager();

            if (!$generalManager) {
                return ['success' => false, 'code' => 422, 'message' => 'No se encontró un GENERAL MANAGER disponible'];
            }

            DB::transaction(function () use ($actor, $changeRequest, $generalManager): void {
                DistributorForecastChangeRequestHistory::create([
                    'distributorForecastChangeRequestId' => $changeRequest->id,
                    'action'                              => 'approved',
                    'actorUserId'                          => $actor->id,
                    'forecast'                             => $changeRequest->proposedForecast,
                    'step'                                 => 'sales_manager',
                ]);

                $changeRequest->update([
                    'currentStep'    => 'general_manager',
                    'approverUserId' => $generalManager->id,
                ]);
            });

            $this->notificationService->notifyDistributorForecastPendingApproval($changeRequest, $distributor?->businessName ?? '');
            $this->notificationService->notifyDistributorForecastStepApproved($changeRequest, $actor, $distributor?->businessName ?? '');

            $cc = array_filter([
                (string) ($submitter?->email ?? ''),
                (string) ($forecastAdmin?->email ?? ''),
            ]);

            $this->sendEmail(new ForecastPendingApprovalMail(
                approverName:   (string) $generalManager->fullName,
                submitterName:  (string) ($submitter?->fullName ?? ''),
                clientId:       (int) $changeRequest->distributorId,
                clientName:     $distributor?->businessName ?? '',
                month:          (int) $changeRequest->month,
                year:           (int) $changeRequest->year,
                proposedAmount: (string) $changeRequest->proposedForecast,
                previousAmount: (string) $changeRequest->previousForecast,
            ), (string) $generalManager->email, cc: $cc);

            return ['success' => true, 'message' => 'Aprobado por SALES MANAGER, pendiente de GENERAL MANAGER'];
        }

        // general_manager: aprobación final — actualiza DistributorForecast
        DB::transaction(function () use ($actor, $changeRequest): void {
            DistributorForecastChangeRequestHistory::create([
                'distributorForecastChangeRequestId' => $changeRequest->id,
                'action'                              => 'approved',
                'actorUserId'                          => $actor->id,
                'forecast'                             => $changeRequest->proposedForecast,
                'step'                                 => 'general_manager',
            ]);

            $changeRequest->update(['status' => 'approved']);

            $this->distributorForecastService->upsertMonth(
                (int) $changeRequest->distributorId,
                (int) $changeRequest->year,
                (int) $changeRequest->month,
                (int) $changeRequest->proposedForecast,
                null
            );
        });

        $this->notificationService->notifyDistributorForecastApproved($changeRequest, $actor, $distributor?->businessName ?? '');

        $clientEmails = $this->getDistributorEmails($distributor);

        $bcc = array_values(array_filter([
            (string) ($distributor?->salesManager?->email ?? ''),
            (string) ($forecastAdmin?->email ?? ''),
        ]));

        $this->sendEmail(new ForecastFinalApprovedMail(
            submitterName:  (string) ($submitter?->fullName ?? ''),
            approverName:   (string) $actor->fullName,
            clientId:       (int) $changeRequest->distributorId,
            clientName:     $distributor?->businessName ?? '',
            month:          (int) $changeRequest->month,
            year:           (int) $changeRequest->year,
            proposedAmount: (string) $changeRequest->proposedForecast,
            previousAmount: (string) $changeRequest->previousForecast,
        ), $clientEmails, bcc: $bcc);

        $this->sendEmail(new ForecastRequestApprovedMail(
            submitterName:  (string) ($submitter?->fullName ?? ''),
            approverName:   (string) $actor->fullName,
            clientId:       (int) $changeRequest->distributorId,
            clientName:     $distributor?->businessName ?? '',
            month:          (int) $changeRequest->month,
            year:           (int) $changeRequest->year,
            proposedAmount: (string) $changeRequest->proposedForecast,
            previousAmount: (string) $changeRequest->previousForecast,
        ), (string) ($submitter?->email ?? ''), cc: array_filter([(string) ($forecastAdmin?->email ?? '')]));

        return ['success' => true, 'message' => 'Objetivo aprobado y aplicado al forecast del distribuidor'];
    }

    // -------------------------------------------------------------------------
    // Reject
    // -------------------------------------------------------------------------

    public function reject(User $actor, int $requestId): array
    {
        $changeRequest = DistributorForecastChangeRequest::find($requestId);

        if (!$changeRequest || $changeRequest->status !== 'pending') {
            return ['success' => false, 'code' => 404, 'message' => 'Solicitud no encontrada o ya procesada'];
        }

        if ((int) $changeRequest->approverUserId !== (int) $actor->id) {
            return ['success' => false, 'code' => 403, 'message' => 'No eres el aprobador designado para esta solicitud'];
        }

        DB::transaction(function () use ($actor, $changeRequest): void {
            DistributorForecastChangeRequestHistory::create([
                'distributorForecastChangeRequestId' => $changeRequest->id,
                'action'                              => 'rejected',
                'actorUserId'                          => $actor->id,
                'forecast'                             => $changeRequest->proposedForecast,
                'step'                                 => $changeRequest->currentStep,
            ]);

            $changeRequest->update(['status' => 'rejected']);
        });

        $distributor   = Distributor::find((int) $changeRequest->distributorId);
        $forecastAdmin = $this->roleService->findForecastAdmin();
        $submitter     = User::find((int) $changeRequest->submittedByUserId);

        $this->notificationService->notifyDistributorForecastRejected($changeRequest, $actor, $distributor?->businessName ?? '');

        $cc = array_filter([(string) ($forecastAdmin?->email ?? '')]);

        $this->sendEmail(new ForecastRejectedMail(
            submitterName:  (string) ($submitter?->fullName ?? ''),
            rejectorName:   (string) $actor->fullName,
            clientId:       (int) $changeRequest->distributorId,
            clientName:     $distributor?->businessName ?? '',
            month:          (int) $changeRequest->month,
            year:           (int) $changeRequest->year,
            proposedAmount: (string) $changeRequest->proposedForecast,
        ), (string) ($submitter?->email ?? ''), cc: $cc);

        return ['success' => true, 'message' => 'Solicitud rechazada'];
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function getPendingForApprover(User $actor): Collection
    {
        return DistributorForecastChangeRequest::where('approverUserId', $actor->id)
            ->where('status', 'pending')
            ->with([
                'submittedBy:id,fullName',
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

    /** @return string[] */
    private function getDistributorEmails(?Distributor $distributor): array
    {
        if (!$distributor || !$distributor->emails) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $distributor->emails))));
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
