<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesAuthenticatedUser
{
    private function resolveAuthenticatedUser(Request $request): ?User
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return null;
        }

        return User::with('role')->find((int) $authUser->id);
    }
}
