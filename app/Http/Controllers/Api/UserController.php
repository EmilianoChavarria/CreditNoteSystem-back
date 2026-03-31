<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSecurity;
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
}
