<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\ForecastChangeRequest;
use App\Models\ForecastSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForecastService
{
    private const CONNECTION        = 'invoices';
    private const CLIENT_TABLE      = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE  = 'clientes_TME700618RC7_ext';
    private const COMPROBANTES_TABLE = 'comprobantes_TME700618RC7';

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
        $extClients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE . ' as cle')
            ->join(self::CLIENT_TABLE . ' as cl', 'cl.idCliente', '=', 'cle.idCliente')
            ->where('cle.salesEngineerId', $salesEngineerId)
            ->where(function ($q) {
                $q->whereIn('cl.ResidenciaFiscal', ['BLZ', 'CRI', 'SLV', 'GTM', 'HND', 'NIC', 'PAN', 'ARG'])
                  ->orWhere('cl.ResidenciaFiscal', '=', '');
            })
            ->select('cle.idCliente', 'cl.razonSocial')
            ->get();

        if ($extClients->isEmpty()) {
            return collect();
        }

        $clientIds        = $extClients->pluck('idCliente')->toArray();
        $forecastMap      = $this->fetchForecast($clientIds, $year);
        $modificationMap  = $this->fetchModifications($clientIds, $year);
        $salesMap         = $this->fetchSales($clientIds, $year);

        return $extClients->map(function ($client) use ($year, $forecastMap, $modificationMap, $salesMap) {
            $key           = (string) $client->idCliente;
            $forecast      = $forecastMap->get($key, collect());
            $modifications = $modificationMap->get($key, collect());
            $sales         = $salesMap->get($key, collect());

            $months = $forecast->keys()
                ->merge($modifications->keys())
                ->merge($sales->keys())
                ->unique()->sort()->values();

            return [
                'idCliente'   => $client->idCliente,
                'razonSocial' => $client->razonSocial,
                'year'        => $year,
                'months'      => $months->map(fn($m) => $this->buildMonthEntry($m, $forecast, $modifications, $sales))->values(),
            ];
        });
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
    public function getInvoicesByMonth(int $idClient, int $month, int $year): Collection
    {
        $rate = $this->banxico->getCurrentUsdRate();

        return DB::connection(self::CONNECTION)
            ->table(self::COMPROBANTES_TABLE)
            ->where('receptorId', $idClient)
            ->where('serie', '')
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->whereMonth('fechaEmision', $month)
            ->select('folio', 'subTotal', 'iva', 'total', 'fechaEmision', 'moneda')
            ->orderBy('fechaEmision')
            ->get()
            ->map(function ($invoice) use ($rate) {
                if ($invoice->moneda === 'MXN') {
                    $invoice->subTotal = round($invoice->subTotal / $rate, 2);
                    $invoice->iva      = round($invoice->iva / $rate, 2);
                    $invoice->total    = round($invoice->total / $rate, 2);
                    $invoice->moneda   = 'USD';
                }
                return $invoice;
            });
    }

    /** Retorna ventas reales (suma de total en USD) indexado por [idClient][month]. */
    private function fetchSales(array $clientIds, int $year): Collection
    {
        $rate = $this->banxico->getCurrentUsdRate();

        return DB::connection(self::CONNECTION)
            ->table(self::COMPROBANTES_TABLE)
            ->whereIn('receptorId', $clientIds)
            ->where('serie', '')
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->selectRaw('receptorId, MONTH(fechaEmision) as month, total, moneda')
            ->get()
            ->groupBy(fn($row) => (string) $row->receptorId)
            ->map(fn($byClient) => $byClient
                ->groupBy('month')
                ->map(function ($rows) use ($rate) {
                    $totalUsd = $rows->sum(
                        fn($r) => $r->moneda === 'MXN' ? $r->total / $rate : (float) $r->total
                    );
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
