<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflowId' => $this->workflowId,
            'stepName' => $this->stepName,
            'stepOrder' => $this->stepOrder,
            'roleId' => $this->roleId,
            'isInitialStep' => $this->isInitialStep,
            'isFinalStep' => $this->isFinalStep,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
            'workflow' => $this->whenLoaded('workflow'),
            'role' => $this->whenLoaded('role'),
            'outgoingTransitions' => $this->whenLoaded('outgoingTransitions'),
        ];
    }
}