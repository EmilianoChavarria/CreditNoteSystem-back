<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestTypePermission;
use App\Services\RequestTypePermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequestTypePermissionController extends Controller
{
    public function __construct(private readonly RequestTypePermissionService $requestTypePermissionService)
    {
    }

    public function assignPermission(Request $request)
    {
        $items = $request->input('permissions', $request->all());

        $validator = Validator::make(['permissions' => $items], [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.role_id' => ['required', 'int', 'exists:roles,id'],
            'permissions.*.request_type_id' => ['required', 'int', 'exists:requesttype,id'],
            'permissions.*.action_id' => ['required', 'int', 'exists:actions,id'],
            'permissions.*.is_allowed' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $result = $this->requestTypePermissionService->upsertPermissions($items);

        return response()->json(ApiResponse::success('Permisos por tipo de solicitud actualizados correctamente', $result));
    }

    public function getAll()
    {
        $permissions = RequestTypePermission::query()
            ->with(['role', 'requestType', 'action'])
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Permisos por tipo de solicitud', $permissions));
    }

    public function getByRole(int $roleId)
    {
        $permissions = RequestTypePermission::query()
            ->with(['role', 'requestType', 'action'])
            ->where('role_id', $roleId)
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Permisos por tipo de solicitud del rol', $permissions));
    }

    public function getByRequestType(int $requestTypeId)
    {
        $permissions = RequestTypePermission::query()
            ->with(['role', 'requestType', 'action'])
            ->where('request_type_id', $requestTypeId)
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Permisos por tipo de solicitud', $permissions));
    }

    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => ['nullable', 'int', 'exists:roles,id'],
            'request_type_id' => ['required', 'int', 'exists:requesttype,id'],
            'action' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $authUser = $request->attributes->get('authUser');
        $roleId = (int) ($request->input('role_id') ?? $authUser?->roleId ?? 0);

        if ($roleId <= 0) {
            return response()->json(ApiResponse::error('No se pudo determinar el rol', null, 422), 422);
        }

        $isAllowed = $this->requestTypePermissionService->canRoleAccess(
            roleId: $roleId,
            requestTypeId: (int) $request->input('request_type_id'),
            action: $request->input('action')
        );

        return response()->json(ApiResponse::success('Validación de permiso por tipo de solicitud', [
            'role_id' => $roleId,
            'request_type_id' => (int) $request->input('request_type_id'),
            'action' => $request->input('action'),
            'is_allowed' => $isAllowed,
        ]));
    }
}
