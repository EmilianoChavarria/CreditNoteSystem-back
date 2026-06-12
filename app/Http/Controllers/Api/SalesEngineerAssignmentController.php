<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesEngineerAssignmentController extends Controller
{
    public function managers(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $managers = User::with('role')
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->whereHas('role', function ($query) {
                $query->whereRaw('UPPER(roleName) LIKE ?', ['%SALES ENGINEER%MANAGER%']);
            })
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Usuarios SALES ENGINEER / MANAGER', UserResource::collection($managers)));
    }

    public function assignableUsers(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $users = User::with('role')
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->whereHas('role', function ($query) {
                $query->whereRaw('UPPER(roleName) = ?', ['SALES ENGINEER']);
            })
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Sales Engineers asignables', UserResource::collection($users)));
    }

    public function index(Request $request, int $managerUserId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $manager = User::with('role')->find($managerUserId);

        if (!$manager || !$manager->isActive || $manager->deletedAt) {
            return response()->json(ApiResponse::error('Manager no disponible', null, 404), 404);
        }

        if (!$this->isSalesEngineerManager($manager)) {
            return response()->json(ApiResponse::error('El usuario seleccionado no es SALES ENGINEER / MANAGER', null, 422), 422);
        }

        $assignedUsers = $manager->assignedUsers()
            ->with('role')
            ->wherePivot('isActive', true)
            ->where('users.isActive', true)
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Sales Engineers asignados al manager', UserResource::collection($assignedUsers)));
    }

    public function upsert(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
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
        $results = [];

        DB::transaction(function () use ($items, &$results): void {
            foreach ($items as $index => $item) {
                $managerUserId  = (int) ($item['managerUserId'] ?? 0);
                $assignedUserId = (int) ($item['assignedUserId'] ?? 0);

                if ($managerUserId === $assignedUserId) {
                    $results[] = [
                        'index'          => $index,
                        'managerUserId'  => $managerUserId,
                        'assignedUserId' => $assignedUserId,
                        'status'         => 'error',
                        'message'        => 'managerUserId y assignedUserId deben ser diferentes',
                    ];
                    continue;
                }

                $manager = User::with('role')->find($managerUserId);
                if (!$manager || !$manager->isActive || $manager->deletedAt) {
                    $results[] = [
                        'index'          => $index,
                        'managerUserId'  => $managerUserId,
                        'assignedUserId' => $assignedUserId,
                        'status'         => 'error',
                        'message'        => 'Manager no disponible',
                    ];
                    continue;
                }

                if (!$this->isSalesEngineerManager($manager)) {
                    $results[] = [
                        'index'          => $index,
                        'managerUserId'  => $managerUserId,
                        'assignedUserId' => $assignedUserId,
                        'status'         => 'error',
                        'message'        => 'El usuario seleccionado no es SALES ENGINEER / MANAGER',
                    ];
                    continue;
                }

                $assignedUser = User::with('role')->find($assignedUserId);
                if (!$assignedUser || !$assignedUser->isActive || $assignedUser->deletedAt) {
                    $results[] = [
                        'index'          => $index,
                        'managerUserId'  => $managerUserId,
                        'assignedUserId' => $assignedUserId,
                        'status'         => 'error',
                        'message'        => 'Usuario a asignar no disponible',
                    ];
                    continue;
                }

                if (!$this->isSalesEngineer($assignedUser)) {
                    $results[] = [
                        'index'          => $index,
                        'managerUserId'  => $managerUserId,
                        'assignedUserId' => $assignedUserId,
                        'status'         => 'error',
                        'message'        => 'Solo se pueden asignar usuarios con rol SALES ENGINEER',
                    ];
                    continue;
                }

                $existing = UserAssignment::query()
                    ->where('leaderUserId', $managerUserId)
                    ->where('assignedUserId', $assignedUserId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (!$existing->isActive) {
                        $existing->update(['isActive' => true]);
                        $status = 'reactivated';
                    } else {
                        $status = 'already_active';
                    }

                    $results[] = [
                        'index'          => $index,
                        'managerUserId'  => $managerUserId,
                        'assignedUserId' => $assignedUserId,
                        'status'         => $status,
                        'assignmentId'   => (int) $existing->id,
                    ];
                    continue;
                }

                $created = UserAssignment::create([
                    'leaderUserId'   => $managerUserId,
                    'assignedUserId' => $assignedUserId,
                    'isActive'       => true,
                ]);

                $results[] = [
                    'index'          => $index,
                    'managerUserId'  => $managerUserId,
                    'assignedUserId' => $assignedUserId,
                    'status'         => 'created',
                    'assignmentId'   => (int) $created->id,
                ];
            }
        });

        return response()->json(ApiResponse::success('Asignaciones procesadas', [
            'total'        => count($items),
            'created'      => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'created')),
            'reactivated'  => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'reactivated')),
            'alreadyActive'=> count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'already_active')),
            'errors'       => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'error')),
            'items'        => $results,
        ]));
    }

    public function destroy(Request $request, int $managerUserId, int $assignedUserId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $manager = User::with('role')->find($managerUserId);

        if (!$manager || !$manager->isActive || $manager->deletedAt) {
            return response()->json(ApiResponse::error('Manager no disponible', null, 404), 404);
        }

        if (!$this->isSalesEngineerManager($manager)) {
            return response()->json(ApiResponse::error('El usuario seleccionado no es SALES ENGINEER / MANAGER', null, 422), 422);
        }

        $assignment = UserAssignment::query()
            ->where('leaderUserId', (int) $manager->id)
            ->where('assignedUserId', $assignedUserId)
            ->where('isActive', true)
            ->first();

        if (!$assignment) {
            return response()->json(ApiResponse::error('Asignación no encontrada', null, 404), 404);
        }

        $assignment->update(['isActive' => false]);

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

    private function isSalesEngineerManager(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return str_contains($roleName, 'SALES ENGINEER') && str_contains($roleName, 'MANAGER');
    }

    private function isSalesEngineer(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return $roleName === 'SALES ENGINEER';
    }

    private function canManageAssignments(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return str_contains($roleName, 'ADMIN') || str_contains($roleName, 'MANAGER');
    }
}
