<?php

namespace App\Services;

use App\Models\Distributor;

class DistributorService
{
    public function upsertByClientNumber(string $clientNumber, array $data): Distributor
    {
        return Distributor::updateOrCreate(
            ['clientNumber' => $clientNumber],
            $data
        );
    }
}
