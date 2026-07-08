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
            'orderStatus'  => $this->orderStatus,
            'notes'        => $this->notes,
            'chargeTypeId' => $this->chargeTypeId,
            'customRate'   => $this->customRate,
            'currency'     => $this->currency,
            'chargeType'   => $this->whenLoaded('chargeType'),
            'createdAt'    => $this->createdAt,
            'updatedAt'    => $this->updatedAt,
            'items'        => ReturnOrderItemResource::collection($this->whenLoaded('items')),
            'linkedRequest' => $this->whenLoaded('returnOrderRequest', function () {
                $link = $this->returnOrderRequest;

                if ($link === null) {
                    return null;
                }

                return [
                    'returnOrderRequestId' => $link->id,
                    'requestId'            => $link->requestId,
                    'requestStatus'        => $link->request?->status,
                    'isFinalized'          => (bool) $link->request?->isFinalized(),
                ];
            }),
        ];
    }
}
