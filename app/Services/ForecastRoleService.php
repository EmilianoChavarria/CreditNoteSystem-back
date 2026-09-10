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

    /**
     * El flujo termina en el SALES ENGINEER MANAGER. FORECAST ADMIN entra aquí
     * porque lo supervisa completo: ve cualquier solicitud pendiente y puede
     * resolverla aunque no sea el aprobador designado.
     */
    public function canApprove(User $user): bool
    {
        return $this->isSalesEngineerManager($user)
            || $this->isForecastAdmin($user);
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
