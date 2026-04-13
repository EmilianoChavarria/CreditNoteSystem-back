<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserSecurity;
use App\Services\PasswordValidationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangeUserPasswordAction
{
    public function __construct(
        private readonly PasswordValidationService $passwordValidationService
    ) {
    }

    public function execute(User $actor, int $targetUserId, string $newPassword): void
    {
        if (!$this->canManagePasswords($actor)) {
            throw ValidationException::withMessages([
                'authorization' => ['No tienes permisos para cambiar la contraseña de otro usuario'],
            ]);
        }

        $user = User::query()->find($targetUserId);

        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['Usuario no encontrado'],
            ]);
        }

        $passwordErrors = $this->passwordValidationService->validatePassword($newPassword);

        if (!empty($passwordErrors)) {
            throw ValidationException::withMessages([
                'errors' => $passwordErrors,
                'requirements' => [$this->passwordValidationService->getRequirements()],
            ]);
        }

        $user->update([
            'passwordHash' => Hash::make($newPassword),
        ]);

        UserSecurity::query()
            ->where('userId', $user->id)
            ->update([
                'sessionToken' => null,
                'lastActivityAt' => now(),
            ]);
    }

    private function canManagePasswords(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return str_contains($roleName, 'ADMIN') || str_contains($roleName, 'MANAGER');
    }
}