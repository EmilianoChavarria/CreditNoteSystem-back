<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'businessName' => $this->businessName,
            'taxId'        => $this->taxId,
            'address'      => $this->address,
            'emails'       => $this->emails,
            'clientNumber' => $this->clientNumber,
            'countrycode'  => $this->countrycode,
        ];
    }
}
