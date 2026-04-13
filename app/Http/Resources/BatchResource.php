<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray($request)
    {
        $total = max(1, (int) $this->totalRecords);
        
        return [
            'id'                => $this->id,
            'fileName'          => $this->fileName,
            'batchType'         => $this->batchType,
            'status'            => $this->status,
            'totalRecords'      => (int) $this->totalRecords,
            'processedRecords'  => (int) $this->processedRecords,
            'processingRecords' => (int) $this->processingRecords,
            'errorRecords'      => (int) $this->errorRecords,
            // Cálculo movido aquí para limpiar el controlador
            'progressPercent'   => round(((int) $this->processedRecords / $total) * 100, 2),
            'createdAt'         => $this->createdAt, 
        ];
    }
}