<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\DistributorForecast;
use App\Models\DistributorForecastChangeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DistributorForecastService
{
    public function getByDistributor(int $distributorId, int $year): Collection
    {
        $forecast = DistributorForecast::where('distributorId', $distributorId)
            ->where('year', $year)
            ->get(['month', 'forecast', 'sales'])
            ->keyBy('month');

        $modifications = $this->fetchModifications([$distributorId], $year)->get((string) $distributorId, collect());

        $months = $forecast->keys()
            ->merge($modifications->keys())
            ->unique()->sort()->values();

        return $months->map(fn($month) => $this->buildMonthEntry($month, $forecast, $modifications))->values();
    }

    /**
     * Distribuidores asignados a un sales engineer, en el mismo formato que
     * ForecastService::getBySalesEngineer() (isGroup/idCliente/razonSocial/year/months)
     * para que el frontend reutilice la misma tabla de clientes extranjeros.
     */
    public function getBySalesEngineer(int $salesEngineerId, int $year): Collection
    {
        $distributors = Distributor::where('salesEngineerId', $salesEngineerId)
            ->orderBy('businessName')
            ->get(['id', 'businessName']);

        if ($distributors->isEmpty()) {
            return collect();
        }

        $distributorIds = $distributors->pluck('id')->all();

        $forecastMap = DistributorForecast::whereIn('distributorId', $distributorIds)
            ->where('year', $year)
            ->get(['distributorId', 'month', 'forecast', 'sales'])
            ->groupBy(fn($row) => (string) $row->distributorId)
            ->map(fn($rows) => $rows->keyBy('month'));

        $modificationMap = $this->fetchModifications($distributorIds, $year);

        return $distributors->map(function ($distributor) use ($year, $forecastMap, $modificationMap) {
            $forecast      = $forecastMap->get((string) $distributor->id, collect());
            $modifications = $modificationMap->get((string) $distributor->id, collect());

            $months = $forecast->keys()
                ->merge($modifications->keys())
                ->unique()->sort()->values();

            return [
                'isGroup'     => false,
                'idCliente'   => $distributor->id,
                'razonSocial' => $distributor->businessName,
                'year'        => $year,
                'months'      => $months->map(fn($m) => $this->buildMonthEntry($m, $forecast, $modifications))->values(),
            ];
        })->values();
    }

    /** Busca clientes extranjeros (distribuidores) por nombre/número de cliente, para el autocomplete de forecast. */
    public function search(string $term): Collection
    {
        return Distributor::where(function ($q) use ($term) {
                $q->where('businessName', 'like', "%{$term}%")
                    ->orWhere('clientNumber', 'like', "%{$term}%");
            })
            ->orderBy('businessName')
            ->limit(20)
            ->get(['id', 'businessName', 'clientNumber'])
            ->map(fn($d) => [
                'tipo'          => 'clienteExtranjero',
                'id'            => $d->id,
                'numeroCliente' => $d->clientNumber,
                'nombre'        => $d->businessName,
            ]);
    }

    /** Resumen de 12 meses de un cliente extranjero (distribuidor): objetivo, venta mensual, %cumplimiento, %retorno (null por ahora). */
    public function getSummary(int $distributorId, int $year): array
    {
        $distributor = Distributor::findOrFail($distributorId);

        $forecast = DistributorForecast::where('distributorId', $distributorId)
            ->where('year', $year)
            ->get(['month', 'forecast', 'sales'])
            ->keyBy('month');

        $meses = [];

        for ($month = 1; $month <= 12; $month++) {
            $row          = $forecast->get($month);
            $objetivo     = $row?->forecast !== null ? (float) $row->forecast : null;
            $ventaMensual = $row?->sales !== null ? (float) $row->sales : null;

            $meses[] = [
                'mes'                    => $month,
                'objetivo'               => $objetivo,
                'ventaMensual'           => $ventaMensual,
                // El cliente extranjero no tiene moneda asignada: su forecast siempre es USD.
                'ventaMensualMoneda'     => $ventaMensual,
                'moneda'                 => ForecastService::DEFAULT_CURRENCY,
                'porcentajeCumplimiento' => ($objetivo > 0 && $ventaMensual !== null)
                    ? round($ventaMensual / $objetivo * 100, 2)
                    : null,
                'porcentajeRetorno'      => null,
            ];
        }

        return [
            'numeroCliente' => $distributor->clientNumber,
            'nombre'        => $distributor->businessName,
            'anio'          => $year,
            'moneda'        => ForecastService::DEFAULT_CURRENCY,
            'meses'         => $meses,
        ];
    }

    /** Retorna la modificación pending/approved más reciente por [distributorId][month]. */
    private function fetchModifications(array $distributorIds, int $year): Collection
    {
        return DistributorForecastChangeRequest::whereIn('distributorId', $distributorIds)
            ->where('year', $year)
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('createdAt')
            ->get(['id', 'distributorId', 'month', 'proposedForecast', 'status', 'currentStep', 'createdAt'])
            ->groupBy(fn($row) => (string) $row->distributorId)
            ->map(fn($rows) => $rows->keyBy('month'));
    }

    private function buildMonthEntry(int $month, Collection $forecast, Collection $modifications): array
    {
        $f = $forecast->get($month);
        $m = $modifications->get($month);

        return [
            'month'        => $month,
            'forecast'     => $f?->forecast,
            'sales'        => $f?->sales,
            'modification' => $m ? [
                'id'               => $m->id,
                'proposedForecast' => $m->proposedForecast,
                'status'           => $m->status,
                'currentStep'      => $m->currentStep,
                'submittedAt'      => $m->createdAt,
            ] : null,
        ];
    }

    public function upsertMonths(int $distributorId, int $year, array $months): Collection
    {
        $now = Carbon::now();

        $rows = array_map(fn($m) => [
            'distributorId' => $distributorId,
            'year'          => $year,
            'month'         => $m['month'],
            'forecast'      => $m['forecast'],
            'sales'         => $m['sales'],
            'createdAt'     => $now,
            'updatedAt'     => $now,
        ], $months);

        DistributorForecast::upsert(
            $rows,
            ['distributorId', 'year', 'month'],
            ['forecast', 'sales', 'updatedAt']
        );

        return $this->getByDistributor($distributorId, $year);
    }

    public function upsertMonth(int $distributorId, int $year, int $month, ?int $forecast, ?int $sales): DistributorForecast
    {
        $row = DistributorForecast::firstOrNew([
            'distributorId' => $distributorId,
            'year'          => $year,
            'month'         => $month,
        ]);

        $row->forecast = $forecast ?? $row->forecast ?? 0;
        $row->sales    = $sales ?? $row->sales ?? 0;
        $row->save();

        return $row;
    }
}
