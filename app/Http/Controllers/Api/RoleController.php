<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('id')->get();

        return response()->json(ApiResponse::success('Roles', $roles));
    }

    public function show(int $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(ApiResponse::error('Rol no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Rol', $role));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roleName' => ['required', 'string', 'max:150', 'unique:roles,roleName'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $role = Role::create([
            'roleName' => $request->input('roleName'),
        ]);

        return response()->json(ApiResponse::success('Rol creado', $role, 201), 201);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'roleName' => [
                'required',
                'string',
                'max:150',
                Rule::unique('roles', 'roleName')->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $role = DB::table('roles')->where('id', $id)->first();

        if (!$role) {
            return response()->json(ApiResponse::error('Rol no encontrado', null, 404), 404);
        }

        DB::table('roles')->where('id', $id)->update([
            'roleName' => $request->input('roleName'),
        ]);

        $role = DB::table('roles')->where('id', $id)->first();

        return response()->json(ApiResponse::success('Rol actualizado', $role));
    }

    public function destroy(int $id)
    {
        $role = DB::table('roles')->where('id', $id)->first();

        if (!$role) {
            return response()->json(ApiResponse::error('Rol no encontrado', null, 404), 404);
        }

        DB::table('roles')->where('id', $id)->delete();

        return response()->json(ApiResponse::success('Rol eliminado'));
    }
}
