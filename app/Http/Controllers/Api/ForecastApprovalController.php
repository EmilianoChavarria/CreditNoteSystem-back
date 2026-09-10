<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forecast\StoreForecastChangeRequest;
use App\Http\Requests\Forecast\StoreForecastChangeRequestBatch;
use App\Services\ForecastApprovalService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ForecastApprovalController extends Controller
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly ForecastApprovalService $approvalService
    ) {
    }

    public function submit(StoreForecastChangeRequest $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canSubmitChange($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para proponer cambios de forecast', null, 403), 403);
        }

        $data   = $request->validated();
        $result = $this->approvalService->submit($actor, $data['idClient'], $data['year'], $data['month'], (float) $data['amount']);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success('Solicitud de cambio enviada', $result['changeRequest']), 201);
    }

    public function submitBatch(StoreForecastChangeRequestBatch $request)
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
        $idClient = (int) $request->query('idClient', 0);
        $year     = (int) $request->query('year', 0);
        $month    = (int) $request->query('month', 0);

        if ($idClient <= 0 || $year <= 0 || $month < 1 || $month > 12) {
            return response()->json(ApiResponse::error('Parámetros inválidos: se requiere idClient, year y month (1-12)', null, 422), 422);
        }

        $history = $this->approvalService->getMonthHistory($idClient, $year, $month);

        return response()->json(ApiResponse::success('Historial de modificaciones', $history));
    }

    /**
     * Aprueba en bloque todas las solicitudes pendientes de un cliente. La
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

    public function approveClientGroup(Request $request, int $idClient)
    {
        return $this->resolveGroup($request, $idClient, true);
    }

    public function rejectClientGroup(Request $request, int $idClient)
    {
        return $this->resolveGroup($request, $idClient, false);
    }

    private function resolveGroup(Request $request, int $idClient, bool $approved)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->canApprove($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para aprobar cambios', null, 403), 403);
        }

        $result = $approved
            ? $this->approvalService->approveClientGroup($actor, $idClient)
            : $this->approvalService->rejectClientGroup($actor, $idClient);

        if (!$result['success']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['code']), $result['code']);
        }

        return response()->json(ApiResponse::success($result['message'], ['resolved' => $result['resolved'] ?? 0]));
    }
}
