<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roleid' => $this->roleid,
            'moduleid' => $this->moduleid,
            'actionid' => $this->actionid,
            'isallowed' => $this->isallowed,
            'role' => $this->whenLoaded('role'),
            'module' => $this->whenLoaded('module'),
            'action' => $this->whenLoaded('action'),
        ];
    }
}