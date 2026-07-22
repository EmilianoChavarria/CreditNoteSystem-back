<?php

namespace App\Services;

use App\Models\DistributorForecast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DistributorForecastService
{
    public function getByDistributor(int $distributorId, int $year): Collection
    {
        return DistributorForecast::where('distributorId', $distributorId)
            ->where('year', $year)
            ->orderBy('month')
            ->get(['month', 'forecast', 'sales'])
            ->map(fn($row) => [
                'month'        => $row->month,
                'forecast'     => $row->forecast,
                'sales'        => $row->sales,
                'modification' => null,
            ])
            ->values();
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
