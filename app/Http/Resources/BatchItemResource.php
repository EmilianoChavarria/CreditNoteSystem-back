<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rowHash' => $this->rowHash,
            'requestId' => $this->requestId,
            'userId' => $this->userId,
            'status' => $this->status,
            'processedAt' => $this->processedAt,
            'errorLog' => is_array($this->errorLog) ? $this->errorLog : null,
            'rawData' => is_array($this->rawData) ? $this->rawData : (json_decode((string) $this->rawData, true) ?: []),
            'request' => RequestResource::make($this->whenLoaded('request')),
        ];
    }
}