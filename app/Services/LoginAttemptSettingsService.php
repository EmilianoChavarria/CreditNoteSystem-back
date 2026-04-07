<?php

namespace App\Services;

use App\Models\LoginAttemptSetting;

class LoginAttemptSettingsService
{
    public function getSettings(): object
    {
        $settings = LoginAttemptSetting::first();

        if ($settings) {
            return $settings;
        }

        return (object) [
            'id' => null,
            'maxUserAttempts' => (int) config('security.max_user_attempts', 5),
            'maxIpAttempts' => (int) config('security.max_ip_attempts', 10),
            'sessionTimeoutMinutes' => (int) config('security.session_timeout_minutes', config('security.jwt_ttl_minutes', 120)),
            'createdAt' => null,
            'updatedAt' => null,
        ];
    }
}