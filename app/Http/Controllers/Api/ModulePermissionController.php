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
        $items = $request->input('permissions', $request->all());

        $validator = Validator::make(['permissions' => $items], [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.requestTypeId' => ['required', 'int', 'exists:requesttype,id'],
            'permissions.*.roleId' => ['required', 'int', 'exists:roles,id'],
            'permissions.*.hasAccess' => ['required', 'boolean']
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $created = 0;
        $updated = 0;
        $processedPermissions = [];

        foreach ($items as $item) {
            $permission = RolePermission::where('requestTypeId', $item['requestTypeId'])
                ->where('roleId', $item['roleId'])
                ->first();

            if ($permission) {
                $permission->update(['hasAccess' => $item['hasAccess']]);
                $updated++;
            } else {
                $permission = RolePermission::create([
                    'requestTypeId' => $item['requestTypeId'],
                    'roleId' => $item['roleId'],
                    'hasAccess' => $item['hasAccess']
                ]);
                $created++;
            }

            $processedPermissions[] = $permission->load(['requesttype', 'role']);
        }

        return response()->json(ApiResponse::success('Permisos actualizados correctamente', [
            'summary' => [
                'total' => count($items),
                'created' => $created,
                'updated' => $updated,
            ],
            'permissions' => $processedPermissions,
        ]), 200);
    }

    public function getAll()
    {
        $permissions = RolePermission::with(['requesttype', 'role'])->orderBy('id')->get();

        return response()->json(ApiResponse::success('Permisos', $permissions));
    }
}
