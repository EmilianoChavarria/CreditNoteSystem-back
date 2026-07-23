<?php

namespace App\Services;

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

        $modifications = $this->fetchModifications($distributorId, $year);

        $months = $forecast->keys()
            ->merge($modifications->keys())
            ->unique()->sort()->values();

        return $months->map(fn($month) => $this->buildMonthEntry($month, $forecast, $modifications))->values();
    }

    /** Retorna la modificación más reciente (pending o approved) por mes. */
    private function fetchModifications(int $distributorId, int $year): Collection
    {
        return DistributorForecastChangeRequest::where('distributorId', $distributorId)
            ->where('year', $year)
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('createdAt')
            ->get(['id', 'month', 'proposedForecast', 'status', 'currentStep', 'createdAt'])
            ->keyBy('month');
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
