<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idProducto' => $this->idProducto,
            'rfc' => $this->rfc,
            'estatus' => $this->estatus,
            'claveProdServ' => $this->claveProdServ,
            'claveUnidad' => $this->claveUnidad,
            'unidadMedida' => $this->unidadMedida,
            'descripcion' => $this->descripcion,
            'valorUnitario' => $this->valorUnitario,
            'clasificacion' => $this->whenLoaded('classification', fn () => $this->classification?->clasificacion),
        ];
    }
}
