<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserSecurity;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(array $data): User
    {
        $user = User::create([
            'fullName' => $data['fullName'],
            'email' => $data['email'],
            'passwordHash' => Hash::make((string) $data['password']),
            'roleId' => (int) $data['roleId'],
            'supervisorId' => $data['supervisorId'] ?? null,
            'preferredLanguage' => $data['preferredLanguage'] ?? 'en',
            'isActive' => $data['isActive'] ?? true,
        ]);

        UserSecurity::create([
            'userId' => $user->id,
            'failedAttempts' => 0,
        ]);

        return $user->load('role');
    }
}