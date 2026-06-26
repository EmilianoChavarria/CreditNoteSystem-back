<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SalesEngineerAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesEngineerAssignmentController extends Controller
{
    public function __construct(
        private readonly SalesEngineerAssignmentService $assignmentService
    ) {
    }

    public function managers(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $managers = $this->assignmentService->getManagers();

        return response()->json(ApiResponse::success('Usuarios SALES ENGINEER / MANAGER', UserResource::collection($managers)));
    }

    public function assignableUsers(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $users = $this->assignmentService->getAssignableUsers();

        return response()->json(ApiResponse::success('Sales Engineers asignables', UserResource::collection($users)));
    }

    public function index(Request $request, int $managerUserId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $manager = User::with('role')->find($managerUserId);

        if (!$manager || !$manager->isActive || $manager->deletedAt) {
            return response()->json(ApiResponse::error('Manager no disponible', null, 404), 404);
        }

        if (!$this->assignmentService->isSalesEngineerManager($manager)) {
            return response()->json(ApiResponse::error('El usuario seleccionado no es SALES ENGINEER / MANAGER', null, 422), 422);
        }

        $assignedUsers = $this->assignmentService->getAssignedUsers($manager);

        return response()->json(ApiResponse::success('Sales Engineers asignados al manager', UserResource::collection($assignedUsers)));
    }

    public function upsert(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $validation = Validator::make($request->all(), [
            'assignments'                    => ['required', 'array', 'min:1'],
            'assignments.*.managerUserId'    => ['required', 'integer', 'exists:users,id'],
            'assignments.*.assignedUserId'   => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validation->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validation->errors(), 422), 422);
        }

        $items   = $request->input('assignments', []);
        $results = $this->assignmentService->processUpsert($items);

        return response()->json(ApiResponse::success('Asignaciones procesadas', [
            'total'        => count($items),
            'created'      => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'created')),
            'reactivated'  => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'reactivated')),
            'alreadyActive'=> count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'already_active')),
            'errors'       => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'error')),
            'items'        => $results,
        ]));
    }

    public function allEngineers(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->isForecastAdmin($actor)) {
            return response()->json(ApiResponse::error('No eres FORECAST ADMIN', null, 403), 403);
        }

        $engineers = $this->assignmentService->getAssignableUsers();

        return response()->json(ApiResponse::success('Todos los Sales Engineers', UserResource::collection($engineers)));
    }

    public function myEngineers(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->isSalesEngineerManager($actor)) {
            return response()->json(ApiResponse::error('No eres SALES ENGINEER / MANAGER', null, 403), 403);
        }

        $engineers = $this->assignmentService->getAssignedUsers($actor);

        return response()->json(ApiResponse::success('Sales Engineers asignados', UserResource::collection($engineers)));
    }

    public function destroy(Request $request, int $managerUserId, int $assignedUserId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->assignmentService->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $manager = User::with('role')->find($managerUserId);

        if (!$manager || !$manager->isActive || $manager->deletedAt) {
            return response()->json(ApiResponse::error('Manager no disponible', null, 404), 404);
        }

        if (!$this->assignmentService->isSalesEngineerManager($manager)) {
            return response()->json(ApiResponse::error('El usuario seleccionado no es SALES ENGINEER / MANAGER', null, 422), 422);
        }

        $deactivated = $this->assignmentService->deactivateAssignment((int) $manager->id, $assignedUserId);

        if (!$deactivated) {
            return response()->json(ApiResponse::error('Asignación no encontrada', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Sales Engineer desasignado correctamente'));
    }

    private function resolveAuthenticatedUser(Request $request): ?User
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return null;
        }

        return User::with('role')->find((int) $authUser->id);
    }
}
