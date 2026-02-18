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
    public function index()
    {
        $users = User::with('role')->orderBy('id')->get();

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

        return response()->json(ApiResponse::success('Usuario creado', $user->load('role'), 201), 201);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'min:6'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $user = $this->findUser($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $now = Carbon::now();
        $update = [
            'fullName' => $request->input('fullName'),
            'email' => $request->input('email'),
            'roleId' => (int) $request->input('roleId'),
            'supervisorId' => $request->input('supervisorId'),
            'preferredLanguage' => $request->input('preferredLanguage', 'en'),
            'isActive' => $request->boolean('isActive', true),
            'updatedAt' => $now,
        ];

        if ($request->filled('password')) {
            $passwordHash = Hash::make($request->input('password'));
            $update['passwordHash'] = $passwordHash;
            $update['password'] = $passwordHash;
        }

        DB::table('users')->where('id', $id)->update($update);

        $user = $this->findUser($id);

        return response()->json(ApiResponse::success('Usuario actualizado', $user));
    }

    public function destroy(int $id)
    {
        $user = $this->findUser($id);

        if (!$user) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $now = Carbon::now();

        DB::table('users')->where('id', $id)->update([
            'passwordHash' => '',
            'isActive' => false,
            'deletedAt' => $now,
            'updatedAt' => $now,
        ]);

        DB::table('userSecurity')->where('userId', $id)->update([
            'sessionToken' => null,
            'lastActivityAt' => $now,
        ]);

        return response()->json(ApiResponse::success('Usuario desactivado'));
    }

    private function findUser(int $id): ?object
    {
        return DB::table('users')
            ->leftJoin('roles', 'users.roleId', '=', 'roles.id')
            ->select(
                'users.id',
                'users.fullName',
                'users.email',
                'users.roleId',
                'roles.roleName',
                'users.supervisorId',
                'users.preferredLanguage',
                'users.isActive',
                'users.deletedAt',
                'users.createdAt',
                'users.updatedAt'
            )
            ->where('users.id', $id)
            ->first();
    }
}
