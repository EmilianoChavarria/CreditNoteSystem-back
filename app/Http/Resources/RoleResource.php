<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roleName' => $this->roleName,
            'color' => $this->color,
            'isActive' => $this->isActive,
            'equivalentroleid' => $this->equivalentroleid,
            'equivalentRole' => $this->whenLoaded('equivalentRole', fn() => [
                'id' => $this->equivalentRole->id,
                'roleName' => $this->equivalentRole->roleName,
            ]),
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}