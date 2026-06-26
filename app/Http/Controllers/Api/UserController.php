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
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
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
        private readonly UserService $userService,
    ) {
    }

    public function managers()
    {
        $users = $this->userService->getUsersByManagerRole();

        $data = $users->map(fn($user) => [
            'id'       => $user->id,
            'fullName' => $user->fullName,
            'role'     => $user->role?->roleName,
        ]);

        return response()->json(ApiResponse::success('Managers obtenidos correctamente', $data));
    }

    public function requesters()
    {
        $users = $this->userService->getUsersByRequesterRole();

        $data = $users->map(fn($user) => [
            'id'       => $user->id,
            'fullName' => $user->fullName,
            'role'     => $user->role?->roleName,
        ]);

        return response()->json(ApiResponse::success('Requesters obtenidos correctamente', $data));
    }

    public function usersBySalesAndManagerRoles()
    {
        $users = $this->userService->getUsersBySalesAndManagerRoles();

        $data = $users->map(fn($user) => [
            'id'       => $user->id,
            'fullName' => $user->fullName,
            'role'     => $user->role?->roleName,
        ]);

        return response()->json(ApiResponse::success('Usuarios por rol obtenidos correctamente', $data));
    }

    public function me(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $user = User::with(['role', 'supervisor'])->find((int) $authUser->id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Perfil del usuario autenticado', UserResource::make($user)));
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
        if (!$actor || !$this->userService->canManagePasswords($actor)) {
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
        $users = $this->userService->getAllActive();

        return response()->json(ApiResponse::success('Usuarios', UserResource::collection($users)));
    }

    public function index(Request $request)
    {
        $perPage  = max(1, (int) $request->query('per_page', 15));
        $search   = trim((string) $request->query('search', ''));
        $roleName = trim((string) $request->query('roleName', $request->query('role_name', '')));

        $users = $this->userService->getPaginated($perPage, $search, $roleName);
        $users->setCollection(UserResource::collection($users->getCollection())->collection);

        return response()->json(ApiResponse::success('Usuarios', $users));
    }

    public function show(int $id)
    {
        $result = $this->userService->getUserWithClient($id);

        if (!$result) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $userData = UserResource::make($result['user'])->resolve();
        if (isset($result['client'])) {
            $userData['client'] = $result['client'];
        }

        return response()->json(ApiResponse::success('Usuario', $userData));
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->createUserAction->execute($request->validated());

        return response()->json(ApiResponse::success('Usuario creado correctamente', UserResource::make($user), 201), 201);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->updateUserAction->execute($id, $request->validated());

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Usuario actualizado', UserResource::make($user)));
    }

    public function destroy(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        if ($this->userService->hasRelatedRecords($user)) {
            return response()->json(ApiResponse::error(
                'No es posible eliminar este usuario porque tiene registros relacionados.',
                null,
                409
            ), 409);
        }

        $this->userService->deactivate($user);

        return response()->json(ApiResponse::success('Usuario desactivado', null, 201));
    }

    public function resendWelcomeEmail(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $isTestOnly = $request->boolean('testOnly');
        $locale = $isTestOnly
            ? (string) $request->input('language', $request->input('locale', $user->preferredLanguage ?? 'es'))
            : (string) ($user->preferredLanguage ?? 'es');

        $this->userService->resendWelcomeEmail($user, $isTestOnly, $locale);

        $message = $isTestOnly
            ? 'Correo de prueba enviado sin actualizar la contraseña del usuario'
            : 'Correo de bienvenida reenviado correctamente';

        return response()->json(ApiResponse::success($message));
    }
}
