<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesEngineerAssignmentService
{
    public function canManageAssignments(User $user): bool
    {
        $roleName = $this->normalizeRole($user);

        return str_contains($roleName, 'ADMIN') || str_contains($roleName, 'MANAGER');
    }

    public function isSalesEngineerManager(User $user): bool
    {
        $roleName = $this->normalizeRole($user);

        return str_contains($roleName, 'SALES ENGINEER') && str_contains($roleName, 'MANAGER');
    }

    public function isSalesEngineer(User $user): bool
    {
        return $this->normalizeRole($user) === 'SALES ENGINEER';
    }

    public function getManagers(): Collection
    {
        return User::with('role')
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->whereHas('role', fn($q) => $q->whereRaw('UPPER(roleName) LIKE ?', ['%SALES ENGINEER%MANAGER%']))
            ->orderBy('fullName')
            ->get();
    }

    public function getAssignableUsers(): Collection
    {
        return User::with('role')
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->whereHas('role', fn($q) => $q->whereRaw('UPPER(roleName) = ?', ['SALES ENGINEER']))
            ->orderBy('fullName')
            ->get();
    }

    public function getAssignedUsers(User $manager): Collection
    {
        return $manager->assignedUsers()
            ->with('role')
            ->wherePivot('isActive', true)
            ->where('users.isActive', true)
            ->orderBy('fullName')
            ->get();
    }

    public function processUpsert(array $items): array
    {
        $results = [];

        DB::transaction(function () use ($items, &$results): void {
            foreach ($items as $index => $item) {
                $managerUserId  = (int) ($item['managerUserId'] ?? 0);
                $assignedUserId = (int) ($item['assignedUserId'] ?? 0);

                if ($managerUserId === $assignedUserId) {
                    $results[] = $this->itemResult($index, $managerUserId, $assignedUserId, 'error', 'managerUserId y assignedUserId deben ser diferentes');
                    continue;
                }

                $manager = User::with('role')->find($managerUserId);
                if (!$manager || !$manager->isActive || $manager->deletedAt) {
                    $results[] = $this->itemResult($index, $managerUserId, $assignedUserId, 'error', 'Manager no disponible');
                    continue;
                }

                if (!$this->isSalesEngineerManager($manager)) {
                    $results[] = $this->itemResult($index, $managerUserId, $assignedUserId, 'error', 'El usuario seleccionado no es SALES ENGINEER / MANAGER');
                    continue;
                }

                $assignedUser = User::with('role')->find($assignedUserId);
                if (!$assignedUser || !$assignedUser->isActive || $assignedUser->deletedAt) {
                    $results[] = $this->itemResult($index, $managerUserId, $assignedUserId, 'error', 'Usuario a asignar no disponible');
                    continue;
                }

                if (!$this->isSalesEngineer($assignedUser)) {
                    $results[] = $this->itemResult($index, $managerUserId, $assignedUserId, 'error', 'Solo se pueden asignar usuarios con rol SALES ENGINEER');
                    continue;
                }

                $existing = UserAssignment::query()
                    ->where('leaderUserId', $managerUserId)
                    ->where('assignedUserId', $assignedUserId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (!$existing->isActive) {
                        $existing->update(['isActive' => true]);
                        $status = 'reactivated';
                    } else {
                        $status = 'already_active';
                    }

                    $results[] = array_merge(
                        $this->itemResult($index, $managerUserId, $assignedUserId, $status),
                        ['assignmentId' => (int) $existing->id]
                    );
                    continue;
                }

                $created = UserAssignment::create([
                    'leaderUserId'   => $managerUserId,
                    'assignedUserId' => $assignedUserId,
                    'isActive'       => true,
                ]);

                $results[] = array_merge(
                    $this->itemResult($index, $managerUserId, $assignedUserId, 'created'),
                    ['assignmentId' => (int) $created->id]
                );
            }
        });

        return $results;
    }

    public function deactivateAssignment(int $managerUserId, int $assignedUserId): bool
    {
        $assignment = UserAssignment::query()
            ->where('leaderUserId', $managerUserId)
            ->where('assignedUserId', $assignedUserId)
            ->where('isActive', true)
            ->first();

        if (!$assignment) {
            return false;
        }

        $assignment->update(['isActive' => false]);

        return true;
    }

    private function normalizeRole(User $user): string
    {
        return mb_strtoupper(trim((string) optional($user->role)->roleName));
    }

    private function itemResult(int $index, int $managerUserId, int $assignedUserId, string $status, ?string $message = null): array
    {
        $result = [
            'index'          => $index,
            'managerUserId'  => $managerUserId,
            'assignedUserId' => $assignedUserId,
            'status'         => $status,
        ];

        if ($message !== null) {
            $result['message'] = $message;
        }

        return $result;
    }
}
