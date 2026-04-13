<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockedIpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ipAddress' => $this->ipAddress,
            'failedAttempts' => $this->failedAttempts,
            'isBlockedPermanently' => $this->isBlockedPermanently,
            'blockedAt' => $this->blockedAt,
            'releasedAt' => $this->releasedAt,
            'reason' => $this->reason ?? null,
            'blockedHistoryAt' => $this->blockedHistoryAt ?? null,
        ];
    }
}