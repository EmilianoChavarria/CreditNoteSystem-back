<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'clientId'       => $this->clientId,
            'userId'         => $this->userId,
            'status'         => $this->status,
            'notes'          => $this->notes,
            'charge'         => $this->charge,
            'chargePolicyId' => $this->chargePolicyId,
            'createdAt'      => $this->createdAt,
            'updatedAt'      => $this->updatedAt,
            'items'          => ReturnOrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
