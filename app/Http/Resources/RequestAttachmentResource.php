<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requestId' => $this->requestId,
            'fileName' => $this->fileName,
            'fileSize' => $this->fileSize,
            'filePath' => $this->filePath,
            'fileExtension' => $this->fileExtension,
            'isActive' => $this->isActive,
            'deletedAt' => $this->deletedAt,
            'createdAt' => $this->createdAt,
        ];
    }
}