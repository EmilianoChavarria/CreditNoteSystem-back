<?php

namespace App\Services;

use App\Models\NationalCustomer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NationalCustomerService
{
    private const CONNECTION = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';

    public function upsertByCustomerNumber(string $customerNumber, array $data): NationalCustomer
    {
        return NationalCustomer::updateOrCreate(
            ['customerNumber' => $customerNumber],
            $data
        );
    }

    public function existsByCustomerNumber(string $customerNumber): bool
    {
        return NationalCustomer::where('customerNumber', $customerNumber)->exists();
    }

    public function existsInInvoices(string $customerNumber): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereRaw('TRIM(CAST(idCliente AS CHAR)) = ?', [$customerNumber])
            ->exists();
    }

    public function getPaginated(int $perPage, string $search): LengthAwarePaginator
    {
        return NationalCustomer::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($sq) =>
                $sq->where('customerNumber', 'like', "%{$search}%")
                    ->orWhere('emails', 'like', "%{$search}%")
            ))
            ->orderBy('customerNumber')
            ->paginate($perPage)
            ->withQueryString();
    }
}
