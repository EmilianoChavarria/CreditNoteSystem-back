<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function upsertPermissions(array $items): array
    {
        $created = 0;
        $updated = 0;
        $permissionIds = [];

        DB::transaction(function () use ($items, &$created, &$updated, &$permissionIds) {
            foreach ($items as $item) {
                $permission = Permission::where('roleid', $item['roleid'])
                    ->where('moduleid', $item['moduleid'])
                    ->where('actionid', $item['actionid'])
                    ->first();

                if ($permission) {
                    $permission->update(['isallowed' => (bool) $item['isallowed']]);
                    $updated++;
                } else {
                    $permission = Permission::create([
                        'roleid' => $item['roleid'],
                        'moduleid' => $item['moduleid'],
                        'actionid' => $item['actionid'],
                        'isallowed' => (bool) $item['isallowed'],
                    ]);
                    $created++;
                }

                $permissionIds[] = $permission->id;
            }
        });

        $permissions = Permission::with(['role', 'module', 'action'])
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

    public function canRoleAccess(int $roleId, int $moduleId, int|string $action): bool
    {
        $actionId = is_numeric($action)
            ? (int) $action
            : (int) Action::query()->where('slug', (string) $action)->value('id');

        if ($actionId <= 0) {
            return false;
        }

        return Permission::query()
            ->where('roleid', $roleId)
            ->where('moduleid', $moduleId)
            ->where('actionid', $actionId)
            ->where('isallowed', true)
            ->exists();
    }

    public function buildSidebarForRole(int $roleId): array
    {
        $modules = Module::query()
            ->select(['id', 'name', 'parentid', 'url', 'icon', 'orderindex', 'requiredactionid'])
            ->with('requiredAction:id,name,slug')
            ->orderBy('orderindex')
            ->orderBy('id')
            ->get();

        $allowedPermissions = Permission::query()
            ->where('roleid', $roleId)
            ->where('isallowed', true)
            ->get(['moduleid', 'actionid']);

        $allowedByModule = $allowedPermissions
            ->groupBy('moduleid')
            ->map(static fn (Collection $rows) => $rows->pluck('actionid')->values()->all());

        $actionIds = $allowedPermissions->pluck('actionid')->unique()->values();
        $actionsById = Action::query()
            ->whereIn('id', $actionIds)
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $childrenByParent = $modules->groupBy(static fn ($module) => $module->parentid ?: 0);

        return $this->buildSidebarNodes(
            parentId: 0,
            childrenByParent: $childrenByParent,
            allowedByModule: $allowedByModule,
            actionsById: $actionsById
        );
    }

    private function buildSidebarNodes(
        int $parentId,
        Collection $childrenByParent,
        Collection $allowedByModule,
        Collection $actionsById
    ): array {
        $result = [];
        $children = $childrenByParent->get($parentId, collect());

        foreach ($children as $module) {
            $childNodes = $this->buildSidebarNodes(
                parentId: (int) $module->id,
                childrenByParent: $childrenByParent,
                allowedByModule: $allowedByModule,
                actionsById: $actionsById
            );

            $allowedActionIds = $allowedByModule->get($module->id, []);
            $hasDirectAccess = !empty($allowedActionIds);
            $requiredActionAllowed = $module->requiredactionid
                ? in_array((int) $module->requiredactionid, $allowedActionIds, true)
                : $hasDirectAccess;

            $isVisible = $module->requiredactionid
                ? $requiredActionAllowed
                : ($hasDirectAccess || !empty($childNodes));

            if (!$isVisible) {
                continue;
            }

            $allowedActions = [];
            foreach ($allowedActionIds as $actionId) {
                $action = $actionsById->get($actionId);
                if (!$action) {
                    continue;
                }

                $allowedActions[] = [
                    'id' => (int) $action->id,
                    'name' => $action->name,
                    'slug' => $action->slug,
                ];
            }

            $result[] = [
                'id' => (int) $module->id,
                'name' => $module->name ?: $module->moduleName,
                'url' => $module->url,
                'icon' => $module->icon,
                'orderIndex' => (int) ($module->orderindex ?? 0),
                'requiredAction' => $module->requiredAction
                    ? [
                        'id' => (int) $module->requiredAction->id,
                        'name' => $module->requiredAction->name,
                        'slug' => $module->requiredAction->slug,
                    ]
                    : null,
                'allowedActions' => $allowedActions,
                'children' => $childNodes,
            ];
        }

        return $result;
    }
}
