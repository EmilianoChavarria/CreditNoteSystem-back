<?php

namespace App\Http\Controllers\Api;

use App\Exports\ForecastGroupInvoicesExport;
use App\Exports\ForecastInvoicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forecast\StoreForecastRequest;
use App\Http\Requests\Forecast\UpdateClientExtRequest;
use App\Http\Requests\Forecast\UpdateForecastEmailsRequest;
use App\Http\Resources\ForecastCreditNoteResource;
use App\Services\DistributorForecastService;
use App\Services\ForecastCreditNoteService;
use App\Services\ForecastService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ForecastController extends Controller
{
    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly DistributorForecastService $distributorForecastService,
        private readonly ForecastCreditNoteService $forecastCreditNoteService
    ) {
    }

    /** Búsqueda por nombre entre distribuidores, clientes extranjeros y grupos, agrupada por sección para autocomplete. */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(ApiResponse::success('Búsqueda de forecast', [
                'clientes'            => [],
                'clientesExtranjeros' => [],
                'grupos'              => [],
            ]));
        }

        $result = [
            'clientes'            => $this->forecastService->searchClients($term)->values(),
            'clientesExtranjeros' => $this->distributorForecastService->search($term)->values(),
            'grupos'              => $this->forecastService->searchGroups($term)->values(),
        ];

        return response()->json(ApiResponse::success('Búsqueda de forecast', $result));
    }

    /**
     * Resumen de 12 meses de un solo cliente/distribuidor/grupo: objetivo, venta mensual,
     * %cumplimiento y %retorno (national_customers.returnPercentage / client_groups.returnPercentage;
     * null para clienteExtranjero, aún sin ese campo).
     */
    public function summary(string $tipo, string $id, int $year)
    {
        try {
            $result = match ($tipo) {
                'cliente'           => $this->forecastService->getClientSummary((int) $id, $year),
                'clienteExtranjero' => $this->distributorForecastService->getSummary((int) $id, $year),
                'grupo'             => $this->forecastService->getGroupSummary($id, $year),
                default             => null,
            };
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(ApiResponse::error('No encontrado', null, 404), 404);
        }

        if ($result === null) {
            return response()->json(ApiResponse::error('Tipo inválido, usa: cliente, clienteExtranjero o grupo', null, 422), 422);
        }

        return response()->json(ApiResponse::success('Resumen de forecast obtenido exitosamente', array_merge(['tipo' => $tipo], $result)));
    }

    /** Genera la NC (registro en requests, tipo auditor credits) por cumplimiento de forecast de un cliente/grupo en un mes. */
    public function generateCreditNote(Request $request, string $tipo, string $id, int $year, int $month)
    {
        $authUser = $request->attributes->get('authUser');

        try {
            $creditNote = $this->forecastCreditNoteService->generate($tipo, $id, $year, $month, $authUser);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return response()->json(ApiResponse::error($message, $e->errors(), 422), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(ApiResponse::error('No encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Nota de crédito generada', ForecastCreditNoteResource::make($creditNote)), 201);
    }

    /** Historial de NC generadas desde forecast para un cliente/grupo. */
    public function creditNoteHistory(string $tipo, string $id)
    {
        if (!in_array($tipo, ['cliente', 'grupo'], true)) {
            return response()->json(ApiResponse::error('Tipo inválido, usa: cliente o grupo', null, 422), 422);
        }

        $history = $this->forecastCreditNoteService->getHistory($tipo, $id);

        return response()->json(ApiResponse::success('Historial de notas de crédito', ForecastCreditNoteResource::collection($history)));
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

    private const TEMPLATE_MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /** Template CSV para carga masiva de forecast: por sales engineer (?salesEngineerId=) o de todos los clientes. */
    public function exportTemplate(Request $request)
    {
        $salesEngineerId = $request->query('salesEngineerId');
        $salesEngineerId = is_numeric($salesEngineerId) ? (int) $salesEngineerId : null;

        $clients = $this->forecastService->getExportTemplateClients($salesEngineerId);
        $year    = now()->year;

        $headers = array_merge(['Customer Number', 'Customer Name', 'Is Group', 'Year'], self::TEMPLATE_MONTHS, ['Total Forecast']);

        $rows = $clients->map(fn (array $client) => array_merge(
            [$client['idCliente'], $client['razonSocial'], $client['isGroup'] ? 'true' : 'false', $year],
            array_fill(0, count(self::TEMPLATE_MONTHS), ''),
            ['']
        ))->values()->all();

        $filename = 'forecast_template_' . ($salesEngineerId ?? 'all') . '_' . $year . '.csv';

        return response($this->buildCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
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

    public function invoiceProductsByMonth(string $idClient, int $year, int $month)
    {
        $data = $this->forecastService->getInvoiceProductsByMonth($idClient, $month, $year);

        return response()->json(ApiResponse::success('Productos por factura del mes', $data));
    }

    public function exportInvoicesByMonth(string $idClient, int $year, int $month)
    {
        if (\App\Models\ClientGroup::where('id', $idClient)->exists()) {
            $data = $this->forecastService->getGroupInvoicesByMonth($idClient, $month, $year);

            $sections = collect($data['sections'])->map(function ($section) use ($month, $year) {
                $section['products'] = $this->productsByFolio((string) $section['clientId'], $month, $year);

                return $section;
            })->all();

            $filename = "facturas_{$data['name']}_{$year}_{$month}.xlsx";

            return Excel::download(
                new ForecastGroupInvoicesExport($sections, $data['name'], $month, $year),
                $filename
            );
        }

        $invoices   = $this->forecastService->getInvoicesByMonth($idClient, $month, $year);
        $clientName = $this->forecastService->getClientName($idClient);

        $filename = "facturas_{$clientName}_{$year}_{$month}.xlsx";

        return Excel::download(
            new ForecastInvoicesExport($invoices, $clientName, $month, $year, null, $this->productsByFolio($idClient, $month, $year), $idClient),
            $filename
        );
    }

    /** [folio => Collection de líneas de producto] para el export en Excel. */
    private function productsByFolio(string $idClient, int $month, int $year): \Illuminate\Support\Collection
    {
        return $this->forecastService->getInvoiceProductsByMonth($idClient, $month, $year)
            ->keyBy('folio')
            ->map(fn($invoice) => collect($invoice['products']));
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
