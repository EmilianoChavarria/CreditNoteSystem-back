<?php

namespace App\Services;

use App\Models\User;

class ForecastRoleService
{
    public function canSubmitChange(User $user): bool
    {
        $role = $this->normalizeRole($user);

        return $role === 'SALES ENGINEER'
            || $this->isSalesEngineerManager($user)
            || $this->isForecastAdmin($user);
    }

    public function canApprove(User $user): bool
    {
        $role = $this->normalizeRole($user);

        return $this->isSalesEngineerManager($user)
            || $role === 'GENERAL MANAGER';
    }

    public function isForecastAdmin(User $user): bool
    {
        return str_contains($this->normalizeRole($user), 'FORECAST ADMIN');
    }

    public function isSalesEngineerManager(User $user): bool
    {
        $role = $this->normalizeRole($user);

        return str_contains($role, 'SALES ENGINEER') && str_contains($role, 'MANAGER');
    }

    public function findGeneralManager(): ?User
    {
        return User::whereHas('role', fn($q) => $q->whereRaw('UPPER(roleName) = ?', ['GENERAL MANAGER']))
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->first();
    }

    public function findForecastAdmin(): ?User
    {
        return User::whereHas('role', fn($q) => $q->whereRaw('UPPER(roleName) LIKE ?', ['%FORECAST ADMIN%']))
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->first();
    }

    public function normalizeRole(User $user): string
    {
        return mb_strtoupper(trim((string) optional($user->role)->roleName));
    }
}
