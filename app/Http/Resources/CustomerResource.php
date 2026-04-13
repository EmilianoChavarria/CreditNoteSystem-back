<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'idCustomer' => $this->idCustomer,
            'idClient' => $this->idClient,
            'area' => $this->area,
            'salesEngineerId' => $this->salesEngineerId,
            'salesManagerId' => $this->salesManagerId,
            'financeManagerId' => $this->financeManagerId,
            'marketingManagerId' => $this->marketingManagerId,
            'customerServiceManagerId' => $this->customerServiceManagerId,
        ];
    }
}