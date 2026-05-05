<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'day'         => $this->day,
            'conditional' => $this->conditional,
            'percentage'  => $this->percentage,
            'createdAt'   => $this->createdAt,
            'updatedAt'   => $this->updatedAt,
            'deletedAt'   => $this->deletedAt,
        ];
    }
}
