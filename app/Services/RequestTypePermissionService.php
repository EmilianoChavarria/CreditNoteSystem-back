<?php

namespace App\Services;

use App\Models\Action;
use App\Models\RequestTypePermission;
use Illuminate\Support\Facades\DB;

class RequestTypePermissionService
{
    public function upsertPermissions(array $items): array
    {
        $created = 0;
        $updated = 0;
        $permissionIds = [];

        DB::transaction(function () use ($items, &$created, &$updated, &$permissionIds) {
            foreach ($items as $item) {
                $permission = RequestTypePermission::query()
                    ->where('role_id', $item['role_id'])
                    ->where('request_type_id', $item['request_type_id'])
                    ->where('action_id', $item['action_id'])
                    ->first();

                if ($permission) {
                    $permission->update(['is_allowed' => (bool) $item['is_allowed']]);
                    $updated++;
                } else {
                    $permission = RequestTypePermission::create([
                        'role_id' => $item['role_id'],
                        'request_type_id' => $item['request_type_id'],
                        'action_id' => $item['action_id'],
                        'is_allowed' => (bool) $item['is_allowed'],
                    ]);
                    $created++;
                }

                $permissionIds[] = $permission->id;
            }
        });

        $permissions = RequestTypePermission::query()
            ->with(['role', 'requestType', 'action'])
            ->whereIn('id', array_unique($permissionIds))
            ->orderBy('id')
            ->get();

        return [
            'summary' => [
                'total' => count($items),
                'created' => $created,
                'updated' => $updated,
            ],
            'permissions' => $permissions,
        ];
    }

    public function canRoleAccess(int $roleId, int $requestTypeId, int|string $action): bool
    {
        $actionId = is_numeric($action)
            ? (int) $action
            : (int) Action::query()->where('slug', (string) $action)->value('id');

        if ($actionId <= 0) {
            return false;
        }

        return RequestTypePermission::query()
            ->where('role_id', $roleId)
            ->where('request_type_id', $requestTypeId)
            ->where('action_id', $actionId)
            ->where('is_allowed', true)
            ->exists();
    }
}
