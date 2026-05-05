<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'roleId' => $this->roleId,
            'supervisorId' => $this->supervisorId,
            'preferredLanguage' => $this->preferredLanguage,
            'isActive' => $this->isActive,
            'clientId' => $this->clientId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
            'role' => $this->whenLoaded('role'),
            'supervisor' => $this->whenLoaded('supervisor'),
        ];
    }
}