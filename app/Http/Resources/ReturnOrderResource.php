<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'clientId'     => $this->clientId,
            'userId'       => $this->userId,
            'status'       => $this->status,
            'notes'        => $this->notes,
            'chargeTypeId' => $this->chargeTypeId,
            'customRate'   => $this->customRate,
            'chargeType'   => $this->whenLoaded('chargeType'),
            'createdAt'    => $this->createdAt,
            'updatedAt'    => $this->updatedAt,
            'items'        => ReturnOrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
