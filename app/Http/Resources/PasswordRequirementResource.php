<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PasswordRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'minLength' => $this->minLength,
            'requireUppercase' => $this->requireUppercase,
            'requireLowercase' => $this->requireLowercase,
            'requireNumbers' => $this->requireNumbers,
            'requireSpecialChars' => $this->requireSpecialChars,
            'allowedSpecialChars' => $this->allowedSpecialChars,
            'expirationDays' => $this->expirationDays,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}