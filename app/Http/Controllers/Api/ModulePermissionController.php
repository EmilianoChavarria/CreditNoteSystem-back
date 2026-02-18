<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModulePermissionController extends Controller
{
    public function assignPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'moduleId' => ['required', 'int', 'exists:modules,id'],
            'roleId' => ['required', 'int', 'exists:roles,id'],
            'hasAccess' => ['required', 'boolean']
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $permission = RolePermission::where('moduleId', $request->input('moduleId'))
            ->where('roleId', $request->input('roleId'))
            ->first();

        if ($permission) {
            $permission->update(['hasAccess' => $request->input('hasAccess')]);
            return response()->json(ApiResponse::success('Permiso actualizado correctamente'), 200);
        }

        $permission = RolePermission::create([
            'moduleId' => $request->input('moduleId'),
            'roleId' => $request->input('roleId'),
            'hasAccess' => $request->input('hasAccess')
        ]);

        return response()->json(ApiResponse::success('Permiso asignado correctamente', $permission->load(['module', 'role']), 201), 201);
    }

    public function getAll()
    {
        $permissions = RolePermission::with(['module', 'role'])->orderBy('id')->get();

        return response()->json(ApiResponse::success('Permisos', $permissions));
    }
}
