<?php

namespace App\Services;

use App\Models\ForecastChangeRequest;
use App\Models\ForecastSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    private const CONNECTION       = 'invoices';
    private const CLIENT_TABLE     = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    public function getByClient(int $idClient, int $year): Collection
    {
        $forecast      = $this->fetchForecast([$idClient], $year)
            ->get($idClient, collect());

        $modifications = $this->fetchModifications([$idClient], $year)
            ->get($idClient, collect());

        $months = $forecast->keys()->merge($modifications->keys())->unique()->sort()->values();

        return $months->map(fn($month) => $this->buildMonthEntry($month, $forecast, $modifications));
    }

    public function getBySalesEngineer(int $salesEngineerId, int $year): Collection
    {
        $extClients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE . ' as cle')
            ->join(self::CLIENT_TABLE . ' as cl', 'cl.idCliente', '=', 'cle.idCliente')
            ->where('cle.salesEngineerId', $salesEngineerId)
            ->select('cle.idCliente', 'cl.razonSocial')
            ->get();

        if ($extClients->isEmpty()) {
            return collect();
        }

        $clientIds     = $extClients->pluck('idCliente')->toArray();
        $forecastMap   = $this->fetchForecast($clientIds, $year);
        $modificationMap = $this->fetchModifications($clientIds, $year);

        return $extClients->map(function ($client) use ($year, $forecastMap, $modificationMap) {
            $key           = (string) $client->idCliente;
            $forecast      = $forecastMap->get($key, collect());
            $modifications = $modificationMap->get($key, collect());

            $months = $forecast->keys()->merge($modifications->keys())->unique()->sort()->values();

            return [
                'idCliente'   => $client->idCliente,
                'razonSocial' => $client->razonSocial,
                'year'        => $year,
                'months'      => $months->map(fn($m) => $this->buildMonthEntry($m, $forecast, $modifications))->values(),
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

    private function buildMonthEntry(int $month, Collection $forecast, Collection $modifications): array
    {
        $f = $forecast->get($month);
        $m = $modifications->get($month);

        return [
            'month'        => $month,
            'amount'       => $f?->amount,
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
