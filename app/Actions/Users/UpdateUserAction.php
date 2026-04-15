<?php

namespace App\Actions\Users;

use App\Models\User;

class UpdateUserAction
{
    public function execute(int $id, array $data): ?User
    {
        $user = User::find($id);

        if (!$user) {
            return null;
        }

        $user->fill([
            'fullName' => $data['fullName'],
            'email' => $data['email'],
            'roleId' => (int) $data['roleId'],
            'supervisorId' => $data['supervisorId'] ?? null,
            'clientId' => $data['clientId'],
            'preferredLanguage' => $data['preferredLanguage'] ?? 'en',
            'isActive' => $data['isActive'] ?? true,
        ]);

        $user->save();

        return $user->load('role');
    }
}