<?php

namespace App\Services;

use App\Models\Action;
use App\Models\RequestTypePermission;

class RequestTypePermissionService
{
    public function upsertPermissions(array $items): array
    {
        $rows = array_map(fn($item) => [
            'role_id'         => (int) $item['role_id'],
            'request_type_id' => (int) $item['request_type_id'],
            'action_id'       => (int) $item['action_id'],
            'is_allowed'      => (bool) $item['is_allowed'],
        ], $items);

        // Single INSERT ... ON DUPLICATE KEY UPDATE — replaces N*2 individual queries
        RequestTypePermission::upsert(
            $rows,
            ['role_id', 'request_type_id', 'action_id'],
            ['is_allowed']
        );

        return [
            'total' => count($items),
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
