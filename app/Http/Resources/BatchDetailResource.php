<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = max(1, (int) $this->totalRecords);

        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'fileName' => $this->fileName,
            'batchType' => $this->batchType,
            'minRange' => $this->minRange,
            'maxRange' => $this->maxRange,
            'status' => $this->status,
            'totalRecords' => (int) $this->totalRecords,
            'processedRecords' => (int) $this->processedRecords,
            'processingRecords' => (int) $this->processingRecords,
            'errorRecords' => (int) $this->errorRecords,
            'createdAt' => $this->createdAt,
            'progressPercent' => round(((int) $this->processedRecords / $total) * 100, 2),
        ];
    }
}