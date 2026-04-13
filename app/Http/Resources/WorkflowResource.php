<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'isActive' => $this->isActive,
            'requestTypeId' => $this->requestTypeId,
            'classificationType' => $this->classificationType,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
            'requestType' => $this->whenLoaded('requestType'),
            'classification' => $this->whenLoaded('classification'),
            'steps' => WorkflowStepResource::collection($this->whenLoaded('steps')),
        ];
    }
}