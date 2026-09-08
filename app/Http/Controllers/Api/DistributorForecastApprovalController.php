<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distributors\StoreDistributorForecastChangeRequest;
use App\Http\Requests\Distributors\StoreDistributorForecastChangeRequestBatch;
use App\Services\DistributorForecastApprovalService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DistributorForecastApprovalController extends Controller
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly DistributorForecastApprovalService $approvalService
    ) {
    }

    public function submit(StoreDistributorForecastChangeRequest $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canSubmitChange($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para proponer cambios de forecast', null, 403), 403);
        }

        $data   = $request->validated();
        $result = $this->approvalService->submit($actor, $data['distributorId'], $data['year'], $data['month'], (int) $data['forecast']);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success('Solicitud de cambio enviada', $result['changeRequest']), 201);
    }

    public function submitBatch(StoreDistributorForecastChangeRequestBatch $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canSubmitChange($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para proponer cambios de forecast', null, 403), 403);
        }

        $result = $this->approvalService->submitBatch($actor, $request->validated()['items']);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], ['errors' => $result['errors'] ?? []], $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success('Solicitudes de cambio enviadas', [
            'created' => $result['created'],
            'errors'  => $result['errors'],
        ]), 201);
    }

    public function pendingForApprover(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canApprove($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para aprobar cambios', null, 403), 403);
        }

        $pending = $this->approvalService->getPendingForApprover($actor);

        return response()->json(ApiResponse::success('Solicitudes pendientes de aprobación', $pending));
    }

    public function myRequests(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $requests = $this->approvalService->getPendingBySubmitter($actor);

        return response()->json(ApiResponse::success('Mis solicitudes de cambio', $requests));
    }

    public function monthHistory(Request $request)
    {
        $distributorId = (int) $request->query('distributorId', 0);
        $year          = (int) $request->query('year', 0);
        $month         = (int) $request->query('month', 0);

        if ($distributorId <= 0 || $year <= 0 || $month < 1 || $month > 12) {
            return response()->json(ApiResponse::error('Parámetros inválidos: se requiere distributorId, year y month (1-12)', null, 422), 422);
        }

        $history = $this->approvalService->getMonthHistory($distributorId, $year, $month);

        return response()->json(ApiResponse::success('Historial de modificaciones', $history));
    }

    /**
     * Aprueba en bloque todas las solicitudes pendientes de un distribuidor. La
     * aprobación de forecast es todo o nada: no se resuelve mes por mes.
     */
    public function approve(Request $request, int $id)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canApprove($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para aprobar cambios', null, 403), 403);
        }

        $result = $this->approvalService->approve($actor, $id);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success($result['message']));
    }

    /** @see self::approve() */
    public function reject(Request $request, int $id)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canApprove($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para rechazar cambios', null, 403), 403);
        }

        $result = $this->approvalService->reject($actor, $id);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success($result['message']));
    }

    public function approveDistributorGroup(Request $request, int $distributorId)
    {
        return $this->resolveGroup($request, $distributorId, true);
    }

    public function rejectDistributorGroup(Request $request, int $distributorId)
    {
        return $this->resolveGroup($request, $distributorId, false);
    }

    private function resolveGroup(Request $request, int $distributorId, bool $approved)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canApprove($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para aprobar cambios', null, 403), 403);
        }

        $result = $approved
            ? $this->approvalService->approveDistributorGroup($actor, $distributorId)
            : $this->approvalService->rejectDistributorGroup($actor, $distributorId);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success($result['message'], ['resolved' => $result['resolved'] ?? 0]));
    }
}
