<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAssignment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserAssignmentController extends Controller
{
    public function leaders(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $leaders = User::with('role')
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->whereHas('role', function ($query) {
                $query->whereRaw('UPPER(roleName) LIKE ?', ['%CS LEADER%']);
            })
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Usuarios CS LEADER', $leaders));
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

        $allowedRoles = ['REQUESTER', 'PROCESSOR', 'REQUESTER / PROCESSOR'];

        $users = User::with('role')
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->whereHas('role', function ($query) use ($allowedRoles) {
                $query->whereIn(DB::raw('UPPER(roleName)'), $allowedRoles);
            })
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Usuarios asignables', $users));
    }

    public function index(Request $request, int $leaderUserId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $leader = User::with('role')->find($leaderUserId);

        if (!$leader || !$leader->isActive || $leader->deletedAt) {
            return response()->json(ApiResponse::error('Líder no disponible', null, 404), 404);
        }

        if (!$this->isCsLeader($leader)) {
            return response()->json(ApiResponse::error('El usuario seleccionado no es CS LEADER', null, 422), 422);
        }

        $assignedUsers = $leader->assignedUsers()
            ->with('role')
            ->wherePivot('isActive', true)
            ->where('users.isActive', true)
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Usuarios asignados al CS LEADER', $assignedUsers));
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
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.leaderUserId' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.assignedUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validation->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validation->errors(), 422), 422);
        }

        $items = $request->input('assignments', []);
        $results = [];

        DB::transaction(function () use ($items, &$results): void {
            foreach ($items as $index => $item) {
                $leaderUserId = (int) ($item['leaderUserId'] ?? 0);
                $assignedUserId = (int) ($item['assignedUserId'] ?? 0);

                if ($leaderUserId === $assignedUserId) {
                    $results[] = [
                        'index' => $index,
                        'leaderUserId' => $leaderUserId,
                        'assignedUserId' => $assignedUserId,
                        'status' => 'error',
                        'message' => 'leaderUserId y assignedUserId deben ser diferentes',
                    ];
                    continue;
                }

                $leader = User::with('role')->find($leaderUserId);
                if (!$leader || !$leader->isActive || $leader->deletedAt) {
                    $results[] = [
                        'index' => $index,
                        'leaderUserId' => $leaderUserId,
                        'assignedUserId' => $assignedUserId,
                        'status' => 'error',
                        'message' => 'Líder no disponible',
                    ];
                    continue;
                }

                if (!$this->isCsLeader($leader)) {
                    $results[] = [
                        'index' => $index,
                        'leaderUserId' => $leaderUserId,
                        'assignedUserId' => $assignedUserId,
                        'status' => 'error',
                        'message' => 'El usuario seleccionado no es CS LEADER',
                    ];
                    continue;
                }

                $assignedUser = User::with('role')->find($assignedUserId);
                if (!$assignedUser || !$assignedUser->isActive || $assignedUser->deletedAt) {
                    $results[] = [
                        'index' => $index,
                        'leaderUserId' => $leaderUserId,
                        'assignedUserId' => $assignedUserId,
                        'status' => 'error',
                        'message' => 'Usuario a asignar no disponible',
                    ];
                    continue;
                }

                if (!$this->isAllowedAssignedRole($assignedUser)) {
                    $results[] = [
                        'index' => $index,
                        'leaderUserId' => $leaderUserId,
                        'assignedUserId' => $assignedUserId,
                        'status' => 'error',
                        'message' => 'Solo se pueden asignar usuarios con rol REQUESTER, PROCESSOR o REQUESTER / PROCESSOR',
                    ];
                    continue;
                }

                $existing = UserAssignment::query()
                    ->where('leaderUserId', $leaderUserId)
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
                        'index' => $index,
                        'leaderUserId' => $leaderUserId,
                        'assignedUserId' => $assignedUserId,
                        'status' => $status,
                        'assignmentId' => (int) $existing->id,
                    ];
                    continue;
                }

                $created = UserAssignment::create([
                    'leaderUserId' => $leaderUserId,
                    'assignedUserId' => $assignedUserId,
                    'isActive' => true,
                ]);

                $results[] = [
                    'index' => $index,
                    'leaderUserId' => $leaderUserId,
                    'assignedUserId' => $assignedUserId,
                    'status' => 'created',
                    'assignmentId' => (int) $created->id,
                ];
            }
        });

        return response()->json(ApiResponse::success('Asignaciones procesadas', [
            'total' => count($items),
            'created' => count(array_filter($results, fn ($row) => ($row['status'] ?? '') === 'created')),
            'reactivated' => count(array_filter($results, fn ($row) => ($row['status'] ?? '') === 'reactivated')),
            'alreadyActive' => count(array_filter($results, fn ($row) => ($row['status'] ?? '') === 'already_active')),
            'errors' => count(array_filter($results, fn ($row) => ($row['status'] ?? '') === 'error')),
            'items' => $results,
        ]));
    }

    public function destroy(Request $request, int $leaderUserId, int $assignedUserId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->canManageAssignments($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para gestionar asignaciones', null, 403), 403);
        }

        $leader = User::with('role')->find($leaderUserId);

        if (!$leader || !$leader->isActive || $leader->deletedAt) {
            return response()->json(ApiResponse::error('Líder no disponible', null, 404), 404);
        }

        if (!$this->isCsLeader($leader)) {
            return response()->json(ApiResponse::error('El usuario seleccionado no es CS LEADER', null, 422), 422);
        }

        $assignment = UserAssignment::query()
            ->where('leaderUserId', (int) $leader->id)
            ->where('assignedUserId', $assignedUserId)
            ->where('isActive', true)
            ->first();

        if (!$assignment) {
            return response()->json(ApiResponse::error('Asignación no encontrada', null, 404), 404);
        }

        $assignment->update(['isActive' => false]);

        return response()->json(ApiResponse::success('Usuario desasignado correctamente'));
    }

    private function resolveAuthenticatedUser(Request $request): ?User
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return null;
        }

        return User::with('role')->find((int) $authUser->id);
    }

    private function isCsLeader(User $user): bool
    {
        $roleName = (string) optional($user->role)->roleName;

        return str_contains(mb_strtoupper($roleName), 'CS LEADER');
    }

    private function isAllowedAssignedRole(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return in_array($roleName, ['REQUESTER', 'PROCESSOR', 'REQUESTER / PROCESSOR'], true);
    }

    private function canManageAssignments(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return str_contains($roleName, 'ADMIN') || str_contains($roleName, 'MANAGER');
    }
}
