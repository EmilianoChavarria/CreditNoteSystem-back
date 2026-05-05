<?php

namespace App\Actions\Auth;

use App\Mail\UserRegisteredMail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurity;
use App\Services\PasswordValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RegisterUserAction
{
    public function __construct(
        private readonly PasswordValidationService $passwordService
    ) {
    }

    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $clientId = $data['clientId'] ?? null;
            $roleId = (int) $data['roleId'];
            $role = Role::query()->select('id', 'roleName')->find($roleId);

            if (!empty($clientId) && strtoupper((string) $role?->roleName) !== 'CUSTOMER') {
                throw ValidationException::withMessages([
                    'clientId' => ['Solo se permite agregar un cliente cuando el rol de usuario es CUSTOMER'],
                ]);
            }

            $password = (string) $data['password'];
            $passwordErrors = $this->passwordService->validatePassword($password);

            if (!empty($passwordErrors)) {
                throw ValidationException::withMessages([
                    'errors' => $passwordErrors,
                    'requirements' => [$this->passwordService->getRequirements()],
                ]);
            }

            $user = User::query()->where('email', $data['email'])->first();
            $passwordHash = Hash::make($password);

            if ($user) {
                if ($user->isActive && is_null($user->deletedAt)) {
                    throw ValidationException::withMessages([
                        'email' => ['El email ya está registrado'],
                    ]);
                }

                $user->update([
                    'fullName' => $data['fullName'],
                    'passwordHash' => $passwordHash,
                    'isActive' => true,
                    'deletedAt' => null,
                    'roleId' => $roleId,
                    'supervisorId' => $data['supervisorId'] ?? null,
                    'preferredLanguage' => $data['preferredLanguage'] ?? 'en',
                    'clientId' => $clientId,
                ]);
            } else {
                $user = User::create([
                    'fullName' => $data['fullName'],
                    'email' => $data['email'],
                    'passwordHash' => $passwordHash,
                    'roleId' => $roleId,
                    'supervisorId' => $data['supervisorId'] ?? null,
                    'preferredLanguage' => $data['preferredLanguage'] ?? 'en',
                    'clientId' => $clientId,
                    'isActive' => true,
                    'deletedAt' => null,
                ]);

                UserSecurity::create([
                    'userId' => $user->id,
                    'failedAttempts' => 0,
                    'lastFailedAt' => null,
                    'lockedUntil' => null,
                    'lastKnownIp' => null,
                    'sessionToken' => null,
                    'lastActivityAt' => null,
                    'lastLoginAt' => null,
                ]);

                Mail::to($data['email'])->send(new UserRegisteredMail(
                    (string) $data['fullName'],
                    (string) $data['email'],
                    $password,
                    (string) ($data['preferredLanguage'] ?? 'es')
                ));
            }

            return [
                'id' => (int) $user->id,
                'fullName' => (string) $user->fullName,
                'email' => (string) $user->email,
                'roleId' => (int) $user->roleId,
                'user' => $user,
            ];
        });
    }
}