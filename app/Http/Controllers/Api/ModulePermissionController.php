<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ModulePermissionController extends Controller
{
    function assignPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'moduleId' => ['required', 'int', 'exists:modules,id'],
            'roleId' => ['required', 'int', 'exists:roles,id'],
            'hasAccess' => ['required', 'boolean']
        ]);


        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $moduleId = $request->input('moduleId');
        $roleId = $request->input('roleId');
        $hasAccess = $request->input('hasAccess');

        // Verificar si el registro ya existe
        $existingPermission = DB::table('rolespermission')
            ->where('moduleId', $moduleId)
            ->where('roleId', $roleId)
            ->first();

        if ($existingPermission) {
            // Si existe, actualizar el campo hasAccess
            DB::table('rolespermission')
                ->where('moduleId', $moduleId)
                ->where('roleId', $roleId)
                ->update([
                    'hasAccess' => $hasAccess
                ]);

            return response()->json(ApiResponse::success('Permiso actualizado correctamente'), 200);
        } else {
            // Si no existe, crear un nuevo registro
            $id = DB::table('rolespermission')->insertGetId([
                'moduleId' => $moduleId,
                'roleId' => $roleId,
                'hasAccess' => $hasAccess
            ]);

            $permission = DB::table('rolespermission')->where('id', $id)->first();

            return response()->json(ApiResponse::success('Permiso asignado correctamente', $permission), 201);
        }
    }

    function getAll()
    {
        $permissions = DB::table('rolespermission')
            ->join('modules', 'rolespermission.moduleId', '=', 'modules.id')
            ->join('roles', 'rolespermission.roleId', '=', 'roles.id')
            ->select(
                'rolespermission.id',
                'rolespermission.moduleId',
                'rolespermission.roleId',
                'rolespermission.hasAccess',
                'modules.moduleName',
                'roles.roleName',
                'rolespermission.created_at',
                'rolespermission.updated_at'
            )
            ->orderBy('rolespermission.id')
            ->get();

        return response()->json(ApiResponse::success('Permisos', $permissions));
    }
}
