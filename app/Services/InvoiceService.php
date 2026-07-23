<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceService
{
    public function getAll(): Collection
    {
        if (!Schema::connection('invoices')->hasTable('comprobantes_TME700618RC7')) {
            return collect();
        }

        return DB::connection('invoices')->table('comprobantes_TME700618RC7')->get();
    }

    public function getInvoicesByClientId(int $clientId): Collection
    {
        if (!Schema::connection('invoices')->hasTable('comprobantes_TME700618RC7')) {
            return collect();
        }

        $query = DB::connection('invoices')->table('comprobantes_TME700618RC7');
        $columns = Schema::connection('invoices')->getColumnListing('comprobantes_TME700618RC7');

        if (in_array('receptorId', $columns, true)) {
            $query->where('receptorId', $clientId)
            ->where('serie', '');
        }

        return $query->get();
    }

    public function searchInvoices(int $clientId, array $filters, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        if (!Schema::connection('invoices')->hasTable('comprobantes_TME700618RC7')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = DB::connection('invoices')->table('comprobantes_TME700618RC7')
            ->where('receptorId', $clientId)
            ->where('serie', '');

        if (!empty($filters['uuid'])) {
            $query->where('UUID', $filters['uuid']);
        }

        if (!empty($filters['folio'])) {
            $query->where('folio', $filters['folio']);
        }

        if (!empty($filters['receptorRfc'])) {
            $query->where('receptorRfc', $filters['receptorRfc']);
        }

        if (!empty($filters['receptorNombre'])) {
            $query->where('receptorNombre', 'LIKE', '%' . $filters['receptorNombre'] . '%');
        }

        if (!empty($filters['moneda'])) {
            $query->where('moneda', $filters['moneda']);
        }

        if (!empty($filters['fechaInicial'])) {
            $query->where('fechaEmision', '>=', Carbon::parse($filters['fechaInicial'])->startOfDay());
        }

        if (!empty($filters['fechaFinal'])) {
            $query->where('fechaEmision', '<=', Carbon::parse($filters['fechaFinal'])->endOfDay());
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getInvoicesByClientIdAndChargeType(string $clientId, string $chargeType, int $perPage = 15, int $page = 1, string $search = '', string $moneda = ''): LengthAwarePaginator
    {
        if (!Schema::connection('invoices')->hasTable('comprobantes_TME700618RC7')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = DB::connection('invoices')->table('comprobantes_TME700618RC7')
            ->where('receptorId', $clientId)
            ->where('serie', '');

        if ($chargeType === 'sporadic') {
            $query->where('fechaEmision', '>=', Carbon::today()->subDays(30)->toDateString());
        }

        if ($chargeType === 'annual') {
            $query->where('fechaEmision', '>=', Carbon::today()->subMonths(30)->toDateString());
        }

        if ($search !== '') {
            $query->where('folio', 'like', "%{$search}%");
        }

        if ($moneda !== '') {
            $query->where('moneda', $moneda);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
