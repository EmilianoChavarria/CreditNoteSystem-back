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
            'color' => ['required', 'string', 'max:10',],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $role = Role::create([
            'roleName' => $request->input('roleName'),
        ]);

        return response()->json(ApiResponse::success('Rol creado correctamente', $role, 201), 201);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'roleName' => [
                'required',
                'string',
                'max:150',
                'unique:roles,roleName'
            ],
            'color' => ['required', 'string', 'max:10',],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $role = Role::find($id);

        if (!$role) {
            return response()->json(ApiResponse::error('Rol no encontrado', null, 404), 404);
        }

        $role->fill([
            'roleName' => $request->input('roleName')
        ]);

        $role->save();

        $role->load('role');

        return response()->json(ApiResponse::success('Rol actualizado correctamente', $role, 201), 201);
    }

    public function destroy(int $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(ApiResponse::error('Rol no encontrado', null, 404), 404);
        }

        $now = now();

        $role->fill([
            'isActive' => false,
            'deletedAt' => $now,
        ]);

        $role->save();

        return response()->json(ApiResponse::success('Rol eliminado correctamente', null, 201), 210);
    }
}
