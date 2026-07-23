<?php

namespace App\Services;

use App\Models\Distributor;
use Illuminate\Pagination\LengthAwarePaginator;

class DistributorService
{
    public function upsertByClientNumber(string $clientNumber, array $data): Distributor
    {
        $distributor = Distributor::updateOrCreate(
            ['clientNumber' => $clientNumber],
            $data
        );

        return $distributor->load(['salesEngineer', 'salesManager']);
    }

    public function existsByClientNumber(string $clientNumber): bool
    {
        return Distributor::where('clientNumber', $clientNumber)->exists();
    }

    public function createByClientNumber(string $clientNumber, array $data): Distributor
    {
        return Distributor::create(array_merge(['clientNumber' => $clientNumber], $data));
    }

    public function getPaginated(int $perPage, string $search, string $zone = ''): LengthAwarePaginator
    {
        return Distributor::query()
            ->with(['salesEngineer', 'salesManager'])
            ->when($search !== '', fn ($q) => $q->where(fn ($sq) =>
                $sq->where('businessName', 'like', "%{$search}%")
                    ->orWhere('taxId', 'like', "%{$search}%")
                    ->orWhere('clientNumber', 'like', "%{$search}%")
                    ->orWhere('emails', 'like', "%{$search}%")
            ))
            ->when($zone === 'argentina', fn ($q) => $q->where('countrycode', 'ARG'))
            ->when($zone === 'centroamerica', fn ($q) => $q->where('countrycode', '!=', 'ARG'))
            ->orderBy('businessName')
            ->paginate($perPage)
            ->withQueryString();
    }
}
