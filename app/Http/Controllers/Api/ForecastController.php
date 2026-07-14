<?php

namespace App\Http\Controllers\Api;

use App\Exports\ForecastGroupInvoicesExport;
use App\Exports\ForecastInvoicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forecast\StoreForecastRequest;
use App\Http\Requests\Forecast\UpdateClientExtRequest;
use App\Http\Requests\Forecast\UpdateForecastEmailsRequest;
use App\Services\ForecastService;
use App\Support\ApiResponse;
use Maatwebsite\Excel\Facades\Excel;

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

    public function clients()
    {
        $perPage = request()->integer('per_page', 15) ?: 15;
        $search  = request()->string('search', '')->trim()->toString();

        $result = $this->forecastService->getPaginatedClients($perPage, $search);

        return response()->json(ApiResponse::success('Clientes forecast', $result));
    }

    public function indexBySalesEngineer(int $salesEngineerId, int $year)
    {
        $result = $this->forecastService->getBySalesEngineer($salesEngineerId, $year);

        return response()->json(ApiResponse::success('Clientes con forecast', $result));
    }

    public function invoicesByMonth(string $idClient, int $year, int $month)
    {
        if (\App\Models\ClientGroup::where('id', $idClient)->exists()) {
            $data = $this->forecastService->getGroupInvoicesByMonth($idClient, $month, $year);
            return response()->json(ApiResponse::success('Facturas del mes por grupo', $data));
        }

        $invoices = $this->forecastService->getInvoicesByMonth($idClient, $month, $year);

        return response()->json(ApiResponse::success('Facturas del mes', $invoices));
    }

    public function exportInvoicesByMonth(string $idClient, int $year, int $month)
    {
        if (\App\Models\ClientGroup::where('id', $idClient)->exists()) {
            $data     = $this->forecastService->getGroupInvoicesByMonth($idClient, $month, $year);
            $filename = "facturas_{$data['name']}_{$year}_{$month}.xlsx";

            return Excel::download(
                new ForecastGroupInvoicesExport($data['sections'], $data['name'], $month, $year),
                $filename
            );
        }

        $invoices   = $this->forecastService->getInvoicesByMonth($idClient, $month, $year);
        $clientName = $this->forecastService->getClientName($idClient);

        $filename = "facturas_{$clientName}_{$year}_{$month}.xlsx";

        return Excel::download(
            new ForecastInvoicesExport($invoices, $clientName, $month, $year),
            $filename
        );
    }

    public function updateClientExt(int $idCliente, UpdateClientExtRequest $request)
    {
        $this->forecastService->updateClientExt($idCliente, $request->validated());

        return response()->json(ApiResponse::success('Datos del cliente actualizados'));
    }

    public function updateClientEmails(int $idCliente, UpdateForecastEmailsRequest $request)
    {
        $this->forecastService->updateClientEmails($idCliente, $request->validated()['emails']);

        return response()->json(ApiResponse::success('Correos actualizados exitosamente'));
    }

    public function store(StoreForecastRequest $request)
    {
        $data  = $request->validated();
        $saved = $this->forecastService->upsert($data['idClient'], $data['year'], $data['months']);

        return response()->json(ApiResponse::success('Forecast guardado exitosamente', $saved), 201);
    }
}
