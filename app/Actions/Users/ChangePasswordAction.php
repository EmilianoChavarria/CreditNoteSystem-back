<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserSecurity;
use App\Services\PasswordValidationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordAction
{
    public function __construct(
        private readonly PasswordValidationService $passwordValidationService
    ) {
    }

    public function execute(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = User::query()->find($userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['Usuario no encontrado'],
            ]);
        }

        if (!Hash::check($currentPassword, (string) $user->passwordHash)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['La contraseña actual no es correcta'],
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
            ->where('userId', $userId)
            ->update([
                'sessionToken' => null,
                'lastActivityAt' => now(),
            ]);
    }
}