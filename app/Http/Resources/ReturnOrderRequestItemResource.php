<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderRequestItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orderItem = $this->returnOrderItem;

        // Campos de solo lectura tomados del returnOrderItem
        $qtyToReturn = $orderItem?->requestedQuantity;
        $unitPrice   = $orderItem?->valorUnitario;
        $subTotal    = ($this->warehouseAccepted !== null && $unitPrice !== null)
            ? round($this->warehouseAccepted * $unitPrice, 2)
            : null;

        return [
            'id'                             => $this->id,
            'returnOrderRequestId'           => $this->returnOrderRequestId,
            'returnOrderItemId'              => $this->returnOrderItemId,

            // Datos de solo lectura del producto
            'partNumber'                     => $this->partNumber,
            'sapId'                          => $this->sapId,
            'qtyToReturn'                    => $qtyToReturn,
            'unitPrice'                      => $unitPrice,
            'subTotal'                       => $subTotal,
            'invoiceFolio'                   => $orderItem?->invoiceFolio,
            'descripcion'                    => $orderItem?->descripcion,
            'claveUnidad'                    => $orderItem?->claveUnidad,
            'unidad'                         => $orderItem?->unidad,

            // Campos del revisor — Replenishment
            'replenishmentAccepted'           => $this->replenishmentAccepted,
            'replenishmentReasonForRejection' => $this->replenishmentReasonForRejection,

            // Campos del revisor — Warehouse
            'warehouseReceived'               => $this->warehouseReceived,
            'warehouseAccepted'               => $this->warehouseAccepted,
            'warehouseReasonForRejection'     => $this->warehouseReasonForRejection,

            'createdAt'                      => $this->createdAt,
            'updatedAt'                      => $this->updatedAt,
        ];
    }
}
