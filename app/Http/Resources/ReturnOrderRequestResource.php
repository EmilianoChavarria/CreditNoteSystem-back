<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () =>
            ReturnOrderRequestItemResource::collection($this->items)
        );

        // Subtotal global: suma de warehouseAccepted * unitPrice por item
        $globalSubTotal = null;
        if ($this->relationLoaded('items')) {
            $globalSubTotal = $this->items->sum(function ($item) {
                $unitPrice = $item->returnOrderItem?->valorUnitario ?? 0;
                return ($item->warehouseAccepted ?? 0) * $unitPrice;
            });
            $globalSubTotal = round($globalSubTotal, 2);
        }

        return [
            'id'                  => $this->id,
            'returnOrderId'       => $this->returnOrderId,
            'requestId'           => $this->requestId,
            'returnChargePercent' => $this->returnChargePercent,
            'globalSubTotal'      => $globalSubTotal,
            'returnChargeAmount'  => $globalSubTotal !== null
                ? round($globalSubTotal * ($this->returnChargePercent / 100), 2)
                : null,
            'createdAt'           => $this->createdAt,
            'updatedAt'           => $this->updatedAt,
            'returnOrder'         => new ReturnOrderResource($this->whenLoaded('returnOrder')),
            'items'               => $items,
        ];
    }
}
