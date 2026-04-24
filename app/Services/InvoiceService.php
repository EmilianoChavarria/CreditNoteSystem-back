<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceService
{
    public function getAll(): Collection
    {
        if (!Schema::hasTable('comprobantes_tme700618rc7')) {
            return collect();
        }

        return DB::table('comprobantes_tme700618rc7')->get();
    }

    public function getInvoicesByClientId(int $clientId): Collection
    {
        if (!Schema::hasTable('comprobantes_tme700618rc7')) {
            return collect();
        }

        $query = DB::table('comprobantes_tme700618rc7');
        $columns = Schema::getColumnListing('comprobantes_tme700618rc7');

        if (in_array('receptorId', $columns, true)) {
            $query->where('receptorId', $clientId)
            ->where('serie', '');
        }

        return $query->get();
    }

    public function searchInvoices(int $clientId, array $filters): Collection
    {
        if (!Schema::hasTable('comprobantes_tme700618rc7')) {
            return collect();
        }

        $query = DB::table('comprobantes_tme700618rc7')
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

        return $query->get();
    }

    public function getInvoicesByClientIdAndChargeType(int $clientId, string $chargeType): Collection
    {
        if (!Schema::hasTable('comprobantes_tme700618rc7')) {
            return collect();
        }

        $query = DB::table('comprobantes_tme700618rc7')
            ->where('receptorId', $clientId)
            ->where('serie', '');

        if ($chargeType === 'sporadic') {
            $query->where('fechaEmision', '>=', Carbon::today()->subDays(30)->toDateString());
        }

        return $query->get();
    }
}
