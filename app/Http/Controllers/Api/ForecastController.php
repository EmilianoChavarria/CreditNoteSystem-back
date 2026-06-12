<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forecast\StoreForecastRequest;
use App\Models\ForecastSale;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;

class ForecastController extends Controller
{
    public function index(int $idClient, int $year)
    {
        $rows = ForecastSale::where('idClient', $idClient)
            ->where('year', $year)
            ->orderBy('month')
            ->get(['month', 'amount']);

        return response()->json(ApiResponse::success('Forecast obtenido exitosamente', $rows));
    }

    public function store(StoreForecastRequest $request)
    {
        $data     = $request->validated();
        $idClient = $data['idClient'];
        $year     = $data['year'];
        $now      = Carbon::now();

        $upserts = array_map(fn($m) => [
            'idClient'   => $idClient,
            'year'       => $year,
            'month'      => $m['month'],
            'amount'     => $m['amount'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $data['months']);

        ForecastSale::upsert(
            $upserts,
            ['idClient', 'year', 'month'],
            ['amount', 'updated_at']
        );

        $saved = ForecastSale::where('idClient', $idClient)
            ->where('year', $year)
            ->orderBy('month')
            ->get(['month', 'amount']);

        return response()->json(ApiResponse::success('Forecast guardado exitosamente', $saved), 201);
    }
}
