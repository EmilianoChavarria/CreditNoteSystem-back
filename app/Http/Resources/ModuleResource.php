<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parentid' => $this->parentid,
            'url' => $this->url,
            'icon' => $this->icon,
            'orderindex' => $this->orderindex,
            'requiredactionid' => $this->requiredactionid,
            'requiredAction' => $this->whenLoaded('requiredAction'),
        ];
    }
}