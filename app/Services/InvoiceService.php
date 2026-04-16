<?php

namespace App\Services;

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
}
