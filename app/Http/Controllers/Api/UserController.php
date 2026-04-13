<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\ChangePasswordAction;
use App\Actions\Users\ChangeUserPasswordAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\ChangeUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly ChangePasswordAction $changePasswordAction,
        private readonly ChangeUserPasswordAction $changeUserPasswordAction,
    ) {
    }

    public function usersBySalesAndManagerRoles()
    {
        $allowedRoles = [
            'SALES ENGINEER / MANAGER',
            'SALES ENGINEER',
            'MANAGER',
        ];

        $users = User::with('role')
            ->where('isActive', '1')
            ->whereHas('role', function ($query) use ($allowedRoles) {
                $query->whereIn('roleName', $allowedRoles);
            })
            ->orderBy('fullName')
            ->get();

        return response()->json(ApiResponse::success('Usuarios por rol obtenidos correctamente', $users));
    }

    public function me(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $user = User::with([
            'role',
            'supervisor',
        ])->find((int) $authUser->id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Perfil del usuario autenticado', $user));
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        try {
            $this->changePasswordAction->execute(
                (int) $authUser->id,
                (string) $request->input('currentPassword'),
                (string) $request->input('newPassword')
            );

            return response()->json(ApiResponse::success('Contraseña actualizada correctamente. Inicia sesión nuevamente.'));
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Datos inválidos', $e->errors(), 422), 422);
        }
    }

    public function changePasswordByUserId(ChangeUserPasswordRequest $request, int $id)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $actor = User::with('role')->find((int) $authUser->id);
        if (!$actor || !$this->canManagePasswords($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para cambiar la contraseña de otro usuario', null, 403), 403);
        }

        try {
            $this->changeUserPasswordAction->execute($actor, $id, (string) $request->input('newPassword'));

            return response()->json(ApiResponse::success('Contraseña del usuario actualizada correctamente.'));
        } catch (ValidationException $e) {
            $errors = $e->errors();

            if (isset($errors['authorization'])) {
                return response()->json(ApiResponse::error('No tienes permisos para cambiar la contraseña de otro usuario', null, 403), 403);
            }

            if (isset($errors['user'])) {
                return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
            }

            return response()->json(ApiResponse::error('Datos inválidos', $errors, 422), 422);
        }
    }

    public function getAll()
    {

        $users = User::with('role')->where('isActive', '1')
            ->get();

        return response()->json(ApiResponse::success('Usuarios', $users));
    }

    public function index(Request $request)
    {
        $perPage = $request->query('perPage');
        $search = trim((string) $request->query('search', ''));

        $users = User::with('role')
            ->where('isActive', '1')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('fullName', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('role', function ($roleQuery) use ($search) {
                            $roleQuery->where('roleName', 'like', "%{$search}%");
                        });
                });
            })
            ->cursorPaginate($perPage);

        return response()->json(ApiResponse::success('Usuarios', $users));
    }

    public function show(int $id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Usuario', $user));
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->createUserAction->execute($request->validated());

        return response()->json(ApiResponse::success('Usuario creado correctamente', $user, 201), 201);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->updateUserAction->execute($id, $request->validated());

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Usuario actualizado', $user));
    }

    public function destroy(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $now = now();

        $user->fill([
            'passwordHash' => '',
            'isActive' => false,
            'deletedAt' => $now,
        ]);

        $user->save();

        $security = $user->security;

        if ($security) {
            $security->update([
                'sessionToken' => null,
                'lastActivityAt' => $now,
            ]);
        }

        return response()->json(ApiResponse::success('Usuario desactivado', null, 201));
    }

    private function findUser(int $id): ?object
    {
        return User::with('role')->find($id);
    }

    private function canManagePasswords(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return str_contains($roleName, 'ADMIN') || str_contains($roleName, 'MANAGER');
    }
}
