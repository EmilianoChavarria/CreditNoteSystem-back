<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'roleId' => $this->roleId,
            'isActive' => $this->isActive,
            'blockedReason' => $this->reason ?? null,
            'blockedAt' => $this->blockedAt ?? null,
            'role' => $this->whenLoaded('role'),
            'security' => $this->whenLoaded('security'),
        ];
    }
}