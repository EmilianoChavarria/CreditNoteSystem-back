<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NationalCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'customerNumber'   => $this->customerNumber,
            'emails'           => $this->emails,
            'returnPercentage' => $this->returnPercentage,
        ];
    }
}
