<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RequestReasonResource;

class RequestClassificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'requestTypes' => RequestTypeResource::collection($this->whenLoaded('requestTypes')),
            'reasons' => RequestReasonResource::collection($this->whenLoaded('reasons')),
        ];
    }
}