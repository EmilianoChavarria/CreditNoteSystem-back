<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Services\PermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModulePermissionController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService)
    {
    }

    public function assignPermission(Request $request)
    {
        $items = $request->input('permissions', $request->all());

        $validator = Validator::make(['permissions' => $items], [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.roleid' => ['required', 'int', 'exists:roles,id'],
            'permissions.*.moduleid' => ['required', 'int', 'exists:modules,id'],
            'permissions.*.actionid' => ['required', 'int', 'exists:actions,id'],
            'permissions.*.isallowed' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $result = $this->permissionService->upsertPermissions($items);

        return response()->json(ApiResponse::success('Permisos actualizados correctamente', $result), 200);
    }

    public function getAll()
    {
        $permissions = Permission::query()
            ->with(['role', 'module', 'action'])
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Permisos', PermissionResource::collection($permissions)));
    }

    public function getByRole(int $roleId)
    {
        $permissions = Permission::query()
            ->with(['role', 'module', 'action'])
            ->where('roleid', $roleId)
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Permisos por rol', PermissionResource::collection($permissions)));
    }

    public function getSidebarByRole(int $roleId)
    {
        $sidebar = $this->permissionService->buildSidebarForRole($roleId);

        return response()->json(ApiResponse::success('Sidebar por rol', $sidebar));
    }

    public function getSidebarForCurrentUser(Request $request)
    {
        $authUser = $request->attributes->get('authUser');
        $roleId = (int) ($authUser?->roleId ?? 0);

        if ($roleId <= 0) {
            return response()->json(ApiResponse::error('No se pudo determinar el rol del usuario autenticado', null, 422), 422);
        }

        $sidebar = $this->permissionService->buildSidebarForRole($roleId);

        return response()->json(ApiResponse::success('Sidebar del usuario autenticado', [
            'roleid' => $roleId,
            'sidebar' => $sidebar,
        ]));
    }

    public function canAccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roleid' => ['nullable', 'int', 'exists:roles,id'],
            'moduleid' => ['required', 'int', 'exists:modules,id'],
            'action' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $authUser = $request->attributes->get('authUser');
        $roleId = (int) ($request->input('roleid') ?? $authUser?->roleId ?? 0);

        if ($roleId <= 0) {
            return response()->json(ApiResponse::error('No se pudo determinar el rol', null, 422), 422);
        }

        $allowed = $this->permissionService->canRoleAccess(
            roleId: $roleId,
            moduleId: (int) $request->input('moduleid'),
            action: $request->input('action')
        );

        return response()->json(ApiResponse::success('Validación de permiso', [
            'roleid' => $roleId,
            'moduleid' => (int) $request->input('moduleid'),
            'action' => $request->input('action'),
            'isallowed' => $allowed,
        ]));
    }
}
