<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSecurity;
use App\Services\PasswordValidationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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

    public function changePassword(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $validator = Validator::make($request->all(), [
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6', 'different:currentPassword', 'confirmed'],
        ], [
            'newPassword.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $user = User::query()->find((int) $authUser->id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        if (!Hash::check((string) $request->input('currentPassword'), (string) $user->passwordHash)) {
            return response()->json(ApiResponse::error('La contraseña actual no es correcta', null, 422), 422);
        }

        /** @var PasswordValidationService $passwordService */
        $passwordService = app(PasswordValidationService::class);
        $passwordErrors = $passwordService->validatePassword((string) $request->input('newPassword'));

        if (!empty($passwordErrors)) {
            return response()->json(
                ApiResponse::error('La nueva contraseña no cumple con los requisitos', [
                    'errors' => $passwordErrors,
                    'requirements' => $passwordService->getRequirements(),
                ], 422),
                422
            );
        }

        $user->update([
            'passwordHash' => Hash::make((string) $request->input('newPassword')),
        ]);

        UserSecurity::query()
            ->where('userId', (int) $user->id)
            ->update([
                'sessionToken' => null,
                'lastActivityAt' => now(),
            ]);

        return response()->json(ApiResponse::success('Contraseña actualizada correctamente. Inicia sesión nuevamente.'));
    }

    public function changePasswordByUserId(Request $request, int $id)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $actor = User::with('role')->find((int) $authUser->id);
        if (!$actor || !$this->canManagePasswords($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para cambiar la contraseña de otro usuario', null, 403), 403);
        }

        $validator = Validator::make($request->all(), [
            'newPassword' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'newPassword.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $user = User::query()->find($id);
        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        /** @var PasswordValidationService $passwordService */
        $passwordService = app(PasswordValidationService::class);
        $newPassword = (string) $request->input('newPassword');
        $passwordErrors = $passwordService->validatePassword($newPassword);

        if (!empty($passwordErrors)) {
            return response()->json(
                ApiResponse::error('La nueva contraseña no cumple con los requisitos', [
                    'errors' => $passwordErrors,
                    'requirements' => $passwordService->getRequirements(),
                ], 422),
                422
            );
        }

        $user->update([
            'passwordHash' => Hash::make($newPassword),
        ]);

        UserSecurity::query()
            ->where('userId', (int) $user->id)
            ->update([
                'sessionToken' => null,
                'lastActivityAt' => now(),
            ]);

        return response()->json(ApiResponse::success('Contraseña del usuario actualizada correctamente.'));
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
        $users = User::with('role')->where('isActive', '1')
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $user = User::create([
            'fullName' => $request->input('fullName'),
            'email' => $request->input('email'),
            'passwordHash' => Hash::make($request->input('password')),
            'roleId' => (int) $request->input('roleId'),
            'supervisorId' => $request->input('supervisorId'),
            'preferredLanguage' => $request->input('preferredLanguage', 'en'),
            'isActive' => $request->boolean('isActive', true),
        ]);

        UserSecurity::create([
            'userId' => $user->id,
            'failedAttempts' => 0,
        ]);

        return response()->json(ApiResponse::success('Usuario creado correctamente', $user->load('role'), 201), 201);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $user->fill([
            'fullName' => $request->input('fullName'),
            'email' => $request->input('email'),
            'roleId' => (int) $request->input('roleId'),
            'supervisorId' => $request->input('supervisorId'),
            'preferredLanguage' => $request->input('preferredLanguage', 'en'),
            'isActive' => $request->boolean('isActive', true),
        ]);

        $user->save();

        $user->load('role');

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
