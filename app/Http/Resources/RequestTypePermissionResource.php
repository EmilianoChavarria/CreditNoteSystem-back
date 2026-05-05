<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestTypePermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'request_type_id' => $this->request_type_id,
            'action_id' => $this->action_id,
            'is_allowed' => $this->is_allowed,
            'role' => $this->whenLoaded('role'),
            'requestType' => $this->whenLoaded('requestType'),
            'action' => $this->whenLoaded('action'),
        ];
    }
}