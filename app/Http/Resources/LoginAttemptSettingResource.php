<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginAttemptSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maxUserAttempts' => $this->maxUserAttempts,
            'maxIpAttempts' => $this->maxIpAttempts,
            'sessionTimeoutMinutes' => $this->sessionTimeoutMinutes,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}