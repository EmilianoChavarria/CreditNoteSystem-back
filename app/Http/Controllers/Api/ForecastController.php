<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forecast\StoreForecastRequest;
use App\Services\ForecastService;
use App\Support\ApiResponse;

class ForecastController extends Controller
{
    public function __construct(
        private readonly ForecastService $forecastService
    ) {
    }

    public function index(int $idClient, int $year)
    {
        $rows = $this->forecastService->getByClient($idClient, $year);

        return response()->json(ApiResponse::success('Forecast obtenido exitosamente', $rows));
    }

    public function indexBySalesEngineer(int $salesEngineerId, int $year)
    {
        $result = $this->forecastService->getBySalesEngineer($salesEngineerId, $year);

        return response()->json(ApiResponse::success('Clientes con forecast', $result));
    }

    public function invoicesByMonth(int $idClient, int $year, int $month)
    {
        $invoices = $this->forecastService->getInvoicesByMonth($idClient, $month, $year);

        return response()->json(ApiResponse::success('Facturas del mes', $invoices));
    }

    public function store(StoreForecastRequest $request)
    {
        $data  = $request->validated();
        $saved = $this->forecastService->upsert($data['idClient'], $data['year'], $data['months']);

        return response()->json(ApiResponse::success('Forecast guardado exitosamente', $saved), 201);
    }
}
