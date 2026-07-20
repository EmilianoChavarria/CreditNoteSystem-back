<?php

namespace App\Services;

use App\Models\ClientGroup;
use App\Models\ClientGroupMember;
use App\Models\Distributor;
use App\Models\ForecastChangeRequest;
use App\Models\ForecastComprobante;
use App\Models\ForecastComprobanteProducto;
use App\Models\ForecastSale;
use App\Models\ProductClassification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForecastService
{
    private const CONNECTION       = 'invoices';
    private const CLIENT_TABLE     = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    public function __construct(private readonly BanxicoService $banxico) {}

    /** @var string[] */
    private const FORECAST_COUNTRIES = ['BLZ', 'CRI', 'SLV', 'GTM', 'HND', 'NIC', 'PAN', 'ARG'];

    public function updateClientExt(int $idCliente, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $clienteId = (string) $idCliente;

        $exists = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE)
            ->where('idCliente', $clienteId)
            ->exists();

        if ($exists) {
            DB::connection(self::CONNECTION)
                ->table(self::CLIENT_EXT_TABLE)
                ->where('idCliente', $clienteId)
                ->update($data);
        } else {
            DB::connection(self::CONNECTION)
                ->table(self::CLIENT_EXT_TABLE)
                ->insert(array_merge(['idCliente' => $clienteId], $data));
        }
    }

    public function updateClientEmails(int $idCliente, array $emails): void
    {
        if (!Schema::connection(self::CONNECTION)->hasColumn(self::CLIENT_EXT_TABLE, 'correosForecast')) {
            throw new \RuntimeException('La columna correosForecast no existe en ' . self::CLIENT_EXT_TABLE . '. Agrégala a la BD antes de continuar.');
        }

        DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE)
            ->where('idCliente', (string) $idCliente)
            ->update(['correosForecast' => implode(';', $emails)]);
    }

    public function getPaginatedClients(?int $perPage, string $search): LengthAwarePaginator
    {
        $paginator = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE . ' as cl')
            ->leftJoin(self::CLIENT_EXT_TABLE . ' as cle', 'cle.idCliente', '=', 'cl.idCliente')
            ->where(function ($q) {
                $q->whereIn('cl.ResidenciaFiscal', self::FORECAST_COUNTRIES)
                  ->orWhere('cl.ResidenciaFiscal', '=', '');
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('cl.razonSocial', 'like', "%{$search}%")
                        ->orWhere('cl.rfc', 'like', "%{$search}%")
                        ->orWhere('cl.idCliente', 'like', "%{$search}%");
                });
            })
            ->orderBy('cl.idCliente')
            ->select(['cl.idCliente', 'cl.razonSocial', 'cl.direccion', 'cl.rfc', 'cle.correosForecast'])
            ->paginate($perPage ?? 15);

        $clientNumbers = collect($paginator->items())
            ->pluck('idCliente')
            ->map(fn($id) => (string) $id)
            ->all();

        $distributors = Distributor::whereIn('clientNumber', $clientNumbers)
            ->get()
            ->keyBy('clientNumber');

        $paginator->through(function ($client) use ($distributors) {
            $dist = $distributors->get((string) $client->idCliente);

            if ($dist) {
                $client->razonSocial     = $dist->businessName;
                $client->rfc             = $dist->taxId;
                $client->direccion       = $dist->address;
                $client->correosForecast = $dist->emails;
            }

            return $client;
        });

        return $paginator;
    }

    public function getByClient(int $idClient, int $year): Collection
    {
        $forecast      = $this->fetchForecast([$idClient], $year)
            ->get($idClient, collect());

        $modifications = $this->fetchModifications([$idClient], $year)
            ->get($idClient, collect());

        $sales = $this->fetchSales([$idClient], $year)
            ->get($idClient, collect());

        $months = $forecast->keys()
            ->merge($modifications->keys())
            ->merge($sales->keys())
            ->unique()->sort()->values();

        return $months->map(fn($month) => $this->buildMonthEntry($month, $forecast, $modifications, $sales));
    }

    public function getBySalesEngineer(int $salesEngineerId, int $year): Collection
    {
        // Groups whose responsible is this sales engineer — always show ALL their members,
        // regardless of each individual member's own salesEngineerId/country in the ext table.
        $myGroups = ClientGroup::where('responsibleUserId', $salesEngineerId)
            ->with('members')
            ->get();

        $myGroupClientIds = $myGroups->flatMap(fn($g) => $g->members->pluck('clientId'))->unique()->values();

        // Clients belonging to ANY group (any responsible) are never listed as individual entries.
        $allGroupedClientIds = ClientGroupMember::pluck('clientId')->unique()->values();

        $extClients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE . ' as cle')
            ->join(self::CLIENT_TABLE . ' as cl', 'cl.idCliente', '=', 'cle.idCliente')
            ->where('cle.salesEngineerId', $salesEngineerId)
            ->where(function ($q) {
                $q->whereIn('cl.ResidenciaFiscal', self::FORECAST_COUNTRIES)
                  ->orWhere('cl.ResidenciaFiscal', '=', '');
            })
            ->select('cle.idCliente', 'cl.razonSocial')
            ->get();

        if ($extClients->isEmpty() && $myGroupClientIds->isEmpty()) {
            return collect();
        }

        $groupClientNames = $myGroupClientIds->isEmpty() ? [] : $this->fetchClientNames($myGroupClientIds->all());

        $allClientIds = $extClients->pluck('idCliente')->merge($myGroupClientIds)->unique()->values()->all();

        $forecastMap     = $this->fetchForecast($allClientIds, $year);
        $modificationMap = $this->fetchModifications($allClientIds, $year);
        $salesMap        = $this->fetchSales($allClientIds, $year);

        $result = collect();

        foreach ($myGroups as $group) {
            $memberIds = $group->members->pluck('clientId')->unique()->values();

            if ($memberIds->isEmpty()) {
                continue;
            }

            $groupClients = $memberIds->map(function ($cid) use ($extClients, $groupClientNames) {
                $known = $extClients->firstWhere('idCliente', $cid);

                return (object) [
                    'idCliente'   => $cid,
                    'razonSocial' => $known->razonSocial ?? ($groupClientNames[$cid] ?? (string) $cid),
                ];
            });

            $result->push($this->buildGroupEntry($group, $groupClients, $year, $salesMap));
        }

        foreach ($extClients as $client) {
            if ($allGroupedClientIds->contains($client->idCliente)) {
                continue; // belongs to a group — shown above (or under another engineer's group)
            }

            $key           = (string) $client->idCliente;
            $forecast      = $forecastMap->get($key, collect());
            $modifications = $modificationMap->get($key, collect());
            $sales         = $salesMap->get($key, collect());

            $months = $forecast->keys()
                ->merge($modifications->keys())
                ->merge($sales->keys())
                ->unique()->sort()->values();

            $result->push([
                'isGroup'     => false,
                'idCliente'   => $client->idCliente,
                'razonSocial' => $client->razonSocial,
                'year'        => $year,
                'months'      => $months->map(fn($m) => $this->buildMonthEntry($m, $forecast, $modifications, $sales))->values(),
            ]);
        }

        return $result;
    }

    private function buildGroupEntry(
        ClientGroup $group,
        \Illuminate\Support\Collection $groupClients,
        int $year,
        \Illuminate\Support\Collection $salesMap,
    ): array {
        // Forecast and modifications are stored against the group ID (not individual clients)
        $groupForecast     = $this->fetchForecast([$group->id], $year)->get((string) $group->id, collect());
        $groupModifications = $this->fetchModifications([$group->id], $year)->get((string) $group->id, collect());

        // Each child: only sales per month
        $allSalesMonths = collect();

        $clients = $groupClients->map(function ($client) use ($year, $salesMap, &$allSalesMonths) {
            $sales      = $salesMap->get((string) $client->idCliente, collect());
            $salesMonths = $sales->keys()->sort()->values();

            $allSalesMonths = $allSalesMonths->merge($salesMonths);

            return [
                'idCliente'   => $client->idCliente,
                'razonSocial' => $client->razonSocial,
                'year'        => $year,
                'months'      => $salesMonths->map(fn($m) => [
                    'month' => $m,
                    'sales' => round((float) ($sales->get($m)?->total ?? 0), 2),
                ])->values(),
            ];
        })->values();

        // Build summed sales per month keyed by month — matches what buildMonthEntry expects
        $groupSales = collect();
        foreach ($allSalesMonths->unique() as $month) {
            $total = $groupClients->sum(
                fn($c) => (float) ($salesMap->get((string) $c->idCliente)?->get($month)?->total ?? 0)
            );
            $groupSales->put($month, (object) ['total' => round($total, 2)]);
        }

        $allMonths = $groupForecast->keys()
            ->merge($groupModifications->keys())
            ->merge($groupSales->keys())
            ->unique()->sort()->values();

        $groupMonths = $allMonths->map(
            fn($month) => $this->buildMonthEntry($month, $groupForecast, $groupModifications, $groupSales)
        )->values();

        return [
            'isGroup'     => true,
            'id'          => $group->id,
            'razonSocial' => $group->name,
            'year'        => $year,
            'months'      => $groupMonths,
            'clients'     => $clients,
        ];
    }

    public function upsert(int $idClient, int $year, array $months): Collection
    {
        $now = Carbon::now();

        $upserts = array_map(fn($m) => [
            'idClient'  => $idClient,
            'year'      => $year,
            'month'     => $m['month'],
            'amount'    => $m['amount'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ], $months);

        ForecastSale::upsert(
            $upserts,
            ['idClient', 'year', 'month'],
            ['amount', 'updatedAt']
        );

        return $this->getByClient($idClient, $year);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** Retorna forecast indexado por [idClient][month]. */
    private function fetchForecast(array $clientIds, int $year): Collection
    {
        return ForecastSale::whereIn('idClient', $clientIds)
            ->where('year', $year)
            ->get(['idClient', 'month', 'amount'])
            ->groupBy(fn($row) => (string) $row->idClient)
            ->map(fn($rows) => $rows->keyBy('month'));
    }

    /**
     * Retorna la modificación más reciente (pending o approved) por [idClient][month].
     * Si hay varias para el mismo mes, gana la más reciente.
     */
    private function fetchModifications(array $clientIds, int $year): Collection
    {
        return ForecastChangeRequest::whereIn('idClient', $clientIds)
            ->where('year', $year)
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('createdAt')
            ->get(['id', 'idClient', 'month', 'proposedAmount', 'status', 'currentStep', 'createdAt'])
            ->groupBy(fn($row) => (string) $row->idClient)
            ->map(fn($rows) => $rows->keyBy('month')); // keyBy con ASC → el último (más reciente) gana
    }

    /** Retorna facturas individuales de un cliente en un mes/año con totales en USD. */
    public function getClientName(int $idClient): string
    {
        $client = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('idCliente', $idClient)
            ->value('razonSocial');

        return $client ?? (string) $idClient;
    }

    public function getGroupInvoicesByMonth(string $groupId, int $month, int $year): array
    {
        $group   = ClientGroup::with('members')->findOrFail($groupId);
        $members = $group->members->unique('clientId')->values();

        $clientNames = $members->isEmpty() ? [] : DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $members->pluck('clientId')->all())
            ->pluck('razonSocial', 'idCliente')
            ->all();

        $sections = $members->map(fn($m) => [
            'clientId'    => $m->clientId,
            'razonSocial' => $clientNames[$m->clientId] ?? (string) $m->clientId,
            'invoices'    => $this->getInvoicesByMonth($m->clientId, $month, $year),
        ])->values()->all();

        return [
            'isGroup'  => true,
            'id'       => $group->id,
            'name'     => $group->name,
            'month'    => $month,
            'year'     => $year,
            'sections' => $sections,
        ];
    }

    public function getInvoicesByMonth(string $idClient, int $month, int $year): Collection
    {
        $invoices = ForecastComprobante::where('receptorId', (string) $idClient)
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->whereMonth('fechaEmision', $month)
            ->orderBy('fechaEmision')
            ->get(['folio', 'subTotal', 'iva', 'total', 'fechaEmision', 'moneda', 'tipoCambio']);

        if ($invoices->isEmpty()) {
            return $invoices;
        }

        $consideredSubtotalByFolio = $this->consideredSubtotalByFolio(
            (string) $idClient,
            $invoices->pluck('folio')->all()
        );

        $fallbackRate = null;

        return $invoices->map(function ($invoice) use (&$fallbackRate, $consideredSubtotalByFolio) {
            $originalSubTotal = (float) $invoice->subTotal;
            $originalTotal    = (float) $invoice->total;
            // Reconstruye subTotal/iva/total desde las líneas de producto (excluyendo No Rodamientos),
            // prorrateando el IVA con el mismo factor total/subTotal de la factura original.
            $factor = $originalSubTotal > 0 ? $originalTotal / $originalSubTotal : 1;

            $subTotal = round($consideredSubtotalByFolio->get($invoice->folio, 0.0), 2);
            $total    = round($subTotal * $factor, 2);
            $iva      = round($total - $subTotal, 2);

            $invoice->subTotal = $subTotal;
            $invoice->iva      = $iva;
            $invoice->total    = $total;

            if ($invoice->moneda === 'MXN') {
                // Use rate stored at sync time; fall back to current rate for legacy rows
                $rate = $invoice->tipoCambio
                    ? (float) $invoice->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());

                $invoice->originalSubTotal = $invoice->subTotal;
                $invoice->originalIva      = $invoice->iva;
                $invoice->originalTotal    = $invoice->total;
                $invoice->originalMoneda   = 'MXN';
                $invoice->tipoCambio       = $rate;

                $invoice->subTotal = round($invoice->subTotal / $rate, 2);
                $invoice->iva      = round($invoice->iva / $rate, 2);
                $invoice->total    = round($invoice->total / $rate, 2);
                $invoice->moneda   = 'USD';
            }
            return $invoice;
        });
    }

    /** [folio => suma de importe de sus líneas, excluyendo productos No Rodamientos] */
    private function consideredSubtotalByFolio(string $idClient, array $folios): Collection
    {
        $products = ForecastComprobanteProducto::where('receptorId', $idClient)
            ->whereIn('folio', $folios)
            ->get(['folio', 'noIdentificacion', 'importe']);

        $productIds = $products->map(fn($p) => trim($p->noIdentificacion))->unique()->values()->all();

        $excludedProductIds = ProductClassification::whereIn('idProducto', $productIds)
            ->where('clasificacion', ProductClassification::NO_RODAMIENTOS)
            ->pluck('idProducto')
            ->flip();

        return $products
            ->reject(fn($p) => isset($excludedProductIds[trim($p->noIdentificacion)]))
            ->groupBy('folio')
            ->map(fn($lines) => (float) $lines->sum('importe'));
    }

    /**
     * Desglose de productos por factura de un cliente en un mes/año.
     * Cada línea trae su clasificación (Rodamientos / No Rodamientos / null si no está clasificada);
     * el `breakdown` de cada factura resta lo marcado como No Rodamientos del total facturado.
     */
    public function getInvoiceProductsByMonth(string $idClient, int $month, int $year): Collection
    {
        $invoices = ForecastComprobante::where('receptorId', (string) $idClient)
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->whereMonth('fechaEmision', $month)
            ->orderBy('fechaEmision')
            ->get(['folio', 'subTotal', 'total', 'fechaEmision', 'moneda', 'tipoCambio']);

        if ($invoices->isEmpty()) {
            return collect();
        }

        $folios = $invoices->pluck('folio')->all();

        $productsByFolio = ForecastComprobanteProducto::where('receptorId', (string) $idClient)
            ->whereIn('folio', $folios)
            ->orderBy('conceptoIndex')
            ->get(['folio', 'noIdentificacion', 'descripcion', 'cantidad', 'valorUnitario', 'importe'])
            ->groupBy('folio');

        $productIds = $productsByFolio->flatten()
            ->map(fn($p) => trim($p->noIdentificacion))
            ->unique()
            ->values()
            ->all();

        $classifications = ProductClassification::whereIn('idProducto', $productIds)
            ->pluck('clasificacion', 'idProducto');

        $fallbackRate = null;

        return $invoices->map(function ($invoice) use ($productsByFolio, $classifications, &$fallbackRate) {
            $subTotal = (float) $invoice->subTotal;
            $total    = (float) $invoice->total;
            // Las líneas de producto no traen IVA; se prorratea con el mismo factor que fetchSales().
            $factor   = $subTotal > 0 ? $total / $subTotal : 1;

            $rate = null;
            if ($invoice->moneda === 'MXN') {
                $rate = $invoice->tipoCambio
                    ? (float) $invoice->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());
            }

            $lines = $productsByFolio->get($invoice->folio, collect())->map(function ($p) use ($classifications, $factor, $rate) {
                $clasificacion = $classifications[trim($p->noIdentificacion)] ?? null;
                $importeConIva = (float) $p->importe * $factor;
                $importeUsd    = round($rate ? $importeConIva / $rate : $importeConIva, 2);

                return [
                    'noIdentificacion' => $p->noIdentificacion,
                    'descripcion'      => $p->descripcion,
                    'cantidad'         => (float) $p->cantidad,
                    'valorUnitario'    => (float) $p->valorUnitario,
                    'importe'          => round((float) $p->importe, 2),
                    'importeUsd'       => $importeUsd,
                    'clasificacion'    => $clasificacion,
                    'excluido'         => $clasificacion === ProductClassification::NO_RODAMIENTOS,
                ];
            })->values();

            $totalFacturado     = round($lines->sum('importeUsd'), 2);
            $totalNoRodamientos = round($lines->where('excluido', true)->sum('importeUsd'), 2);
            $totalConsiderado   = round($totalFacturado - $totalNoRodamientos, 2);

            return [
                'folio'        => $invoice->folio,
                'fechaEmision' => $invoice->fechaEmision,
                'moneda'       => $rate ? 'USD' : $invoice->moneda,
                'tipoCambio'   => $rate,
                'products'     => $lines,
                'breakdown'    => [
                    'totalFacturado'     => $totalFacturado,
                    'totalNoRodamientos' => $totalNoRodamientos,
                    'totalConsiderado'   => $totalConsiderado,
                ],
            ];
        })->values();
    }

    /** [idCliente => razonSocial] fetched from the external clients table in one query. */
    private function fetchClientNames(array $clientIds): array
    {
        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $clientIds)
            ->pluck('razonSocial', 'idCliente')
            ->all();
    }

    /**
     * Retorna ventas reales (suma de las líneas de producto por factura, en USD) indexado por [idClient][month].
     * Excluye las líneas cuyo producto esté clasificado como No Rodamientos.
     */
    private function fetchSales(array $clientIds, int $year): Collection
    {
        $rate        = $this->banxico->getCurrentUsdRate();
        $receptorIds = array_map('strval', $clientIds);

        $rows = ForecastComprobanteProducto::query()
            ->join('forecastcomprobantes', function ($join) {
                $join->on('forecastcomprobantes.receptorId', '=', 'forecastcomprobanteproductos.receptorId')
                     ->on('forecastcomprobantes.folio', '=', 'forecastcomprobanteproductos.folio');
            })
            ->whereIn('forecastcomprobanteproductos.receptorId', $receptorIds)
            ->where('forecastcomprobantes.status', 'Emitido')
            ->whereYear('forecastcomprobantes.fechaEmision', $year)
            ->selectRaw('forecastcomprobanteproductos.receptorId as receptorId, MONTH(forecastcomprobantes.fechaEmision) as month, forecastcomprobanteproductos.noIdentificacion as noIdentificacion, forecastcomprobanteproductos.importe as importe, forecastcomprobantes.subTotal as subTotal, forecastcomprobantes.total as total, forecastcomprobantes.moneda as moneda')
            ->get();

        $productIds = $rows->map(fn($r) => trim($r->noIdentificacion))->unique()->values()->all();

        $excludedProductIds = ProductClassification::whereIn('idProducto', $productIds)
            ->where('clasificacion', ProductClassification::NO_RODAMIENTOS)
            ->pluck('idProducto')
            ->flip();

        return $rows
            ->reject(fn($r) => isset($excludedProductIds[trim($r->noIdentificacion)]))
            ->groupBy(fn($row) => (string) $row->receptorId)
            ->map(fn($byClient) => $byClient
                ->groupBy('month')
                ->map(function ($rows) use ($rate) {
                    // El importe de cada línea excluye IVA; se prorratea con el factor
                    // total/subTotal de su factura para que la suma cuadre con el total facturado.
                    $totalUsd = $rows->sum(function ($r) use ($rate) {
                        $factor = (float) $r->subTotal > 0 ? (float) $r->total / (float) $r->subTotal : 1;
                        $amount = (float) $r->importe * $factor;

                        return $r->moneda === 'MXN' ? $amount / $rate : $amount;
                    });
                    return (object) ['total' => round($totalUsd, 2)];
                })
            );
    }

    private function buildMonthEntry(int $month, Collection $forecast, Collection $modifications, Collection $sales): array
    {
        $f = $forecast->get($month);
        $m = $modifications->get($month);
        $s = $sales->get($month);

        return [
            'month'        => $month,
            'amount'       => $f?->amount,
            'sales'        => $s?->total,
            'modification' => $m ? [
                'id'             => $m->id,
                'proposedAmount' => $m->proposedAmount,
                'status'         => $m->status,
                'currentStep'    => $m->currentStep,
                'submittedAt'    => $m->createdAt,
            ] : null,
        ];
    }
}
