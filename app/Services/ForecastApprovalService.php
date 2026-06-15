<?php

namespace App\Services;

use App\Models\ForecastChangeRequest;
use App\Models\ForecastChangeRequestHistory;
use App\Models\ForecastSale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForecastApprovalService
{
    private const EXT_CONNECTION   = 'invoices';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    public function __construct(
        private readonly NotificationService $notificationService
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

        $isSalesManager = $this->isSalesEngineerManager($actor);

        if ($isSalesManager) {
            $step     = 'general_manager';
            $approver = $this->findGeneralManager();
        } else {
            $step     = 'sales_manager';
            $approver = $this->findSalesManagerForClient($idClient);
        }

        if (!$approver) {
            $label = $isSalesManager ? 'GENERAL MANAGER' : 'SALES ENGINEER / MANAGER';
            return ['success' => false, 'code' => 422, 'message' => "No se encontró un {$label} disponible para este cliente"];
        }

        $previousAmount = ForecastSale::where('idClient', $idClient)
            ->where('year', $year)
            ->where('month', $month)
            ->value('amount') ?? 0;

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

        $this->notificationService->notifyForecastPendingApproval($changeRequest);

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

        if ($changeRequest->currentStep === 'sales_manager') {
            $generalManager = $this->findGeneralManager();

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

            // $changeRequest->approverUserId ya es generalManager->id tras el update
            $this->notificationService->notifyForecastPendingApproval($changeRequest);
            $this->notificationService->notifyForecastStepApproved($changeRequest, $actor);

            return ['success' => true, 'message' => 'Aprobado por SALES MANAGER, pendiente de GENERAL MANAGER'];
        }

        // general_manager: aprobación final — solo marca como aprobado, el forecast original no se modifica
        DB::transaction(function () use ($actor, $changeRequest): void {
            ForecastChangeRequestHistory::create([
                'forecastChangeRequestId' => $changeRequest->id,
                'action'                  => 'approved',
                'actorUserId'             => $actor->id,
                'amount'                  => $changeRequest->proposedAmount,
                'step'                    => 'general_manager',
            ]);

            $changeRequest->update(['status' => 'approved']);
        });

        $this->notificationService->notifyForecastApproved($changeRequest, $actor);

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

        $this->notificationService->notifyForecastRejected($changeRequest, $actor);

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
        $role = $this->normalizeRole($user);

        return $role === 'SALES ENGINEER'
            || str_contains($role, 'SALES ENGINEER') && str_contains($role, 'MANAGER');
    }

    public function canApprove(User $user): bool
    {
        $role = $this->normalizeRole($user);

        return str_contains($role, 'SALES ENGINEER') && str_contains($role, 'MANAGER')
            || $role === 'GENERAL MANAGER';
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isSalesEngineerManager(User $user): bool
    {
        $role = $this->normalizeRole($user);

        return str_contains($role, 'SALES ENGINEER') && str_contains($role, 'MANAGER');
    }

    private function findGeneralManager(): ?User
    {
        return User::whereHas('role', fn($q) => $q->whereRaw('UPPER(roleName) = ?', ['GENERAL MANAGER']))
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->first();
    }

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

    private function normalizeRole(User $user): string
    {
        return mb_strtoupper(trim((string) optional($user->role)->roleName));
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
