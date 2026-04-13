<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => (int) $this->userId,
            'type' => $this->type,
            'relatedId' => $this->relatedId,
            'title' => $this->title,
            'message' => $this->message,
            'isRead' => (bool) $this->isRead,
            'readAt' => $this->readAt,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}