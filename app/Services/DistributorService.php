<?php

namespace App\Services;

use App\Models\Distributor;
use Illuminate\Pagination\LengthAwarePaginator;

class DistributorService
{
    public function upsertByClientNumber(string $clientNumber, array $data): Distributor
    {
        return Distributor::updateOrCreate(
            ['clientNumber' => $clientNumber],
            $data
        );
    }

    public function getPaginated(int $perPage, string $search): LengthAwarePaginator
    {
        return Distributor::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($sq) =>
                $sq->where('businessName', 'like', "%{$search}%")
                    ->orWhere('taxId', 'like', "%{$search}%")
                    ->orWhere('clientNumber', 'like', "%{$search}%")
                    ->orWhere('emails', 'like', "%{$search}%")
            ))
            ->orderBy('businessName')
            ->paginate($perPage)
            ->withQueryString();
    }
}
