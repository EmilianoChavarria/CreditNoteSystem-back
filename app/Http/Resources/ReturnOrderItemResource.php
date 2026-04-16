<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'returnOrderId'     => $this->returnOrderId,
            'invoiceFolio'      => $this->invoiceFolio,
            'invoiceClientId'   => $this->invoiceClientId,
            'conceptoIndex'     => $this->conceptoIndex,
            'claveProdServ'     => $this->claveProdServ,
            'descripcion'       => $this->descripcion,
            'claveUnidad'       => $this->claveUnidad,
            'unidad'            => $this->unidad,
            'valorUnitario'     => $this->valorUnitario,
            'originalQuantity'  => $this->originalQuantity,
            'requestedQuantity' => $this->requestedQuantity,
            'createdAt'         => $this->createdAt,
        ];
    }
}
