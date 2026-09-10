<?php

namespace App\Http\Controllers\Api;

use App\Exports\ForecastGroupInvoicesExport;
use App\Exports\ForecastInvoicesExport;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forecast\StoreForecastRequest;
use App\Http\Requests\Forecast\UpdateClientExtRequest;
use App\Http\Requests\Forecast\UpdateForecastEmailsRequest;
use App\Http\Resources\ForecastCreditNoteResource;
use App\Models\ForecastAnnualTarget;
use App\Services\DistributorForecastService;
use App\Services\ForecastAnnualTargetService;
use App\Services\ForecastCreditNoteService;
use App\Services\ForecastExportService;
use App\Services\ForecastRoleService;
use App\Services\ForecastService;
use App\Services\SimpleExcelExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ForecastController extends Controller
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly DistributorForecastService $distributorForecastService,
        private readonly ForecastCreditNoteService $forecastCreditNoteService,
        private readonly ForecastExportService $forecastExportService,
        private readonly SimpleExcelExportService $excelExportService,
        private readonly ForecastAnnualTargetService $annualTargets,
        private readonly ForecastRoleService $roleService,
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

        // sharedAttachments[] — un solo set de adjuntos que se aplica a todas las NC generadas
        // (en grupo, la misma documentación soporta la nota de cada cliente miembro).
        $sharedAttachments = $request->file('sharedAttachments') ?? [];
        $sharedAttachments = is_array($sharedAttachments) ? $sharedAttachments : [$sharedAttachments];

        // attachments[{clientId}][] — adjuntos específicos por NC; tienen prioridad sobre los compartidos.
        $attachmentsByClient = $request->file('attachments') ?? [];
        $attachmentsByClient = is_array($attachmentsByClient) ? $attachmentsByClient : [];

        try {
            $result = $this->forecastCreditNoteService->generate($tipo, $id, $year, $month, $authUser, $attachmentsByClient, $sharedAttachments);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return response()->json(ApiResponse::error($message, $e->errors(), 422), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(ApiResponse::error('No encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Nota de crédito generada', [
            'created' => ForecastCreditNoteResource::collection($result['created']),
            'skipped' => $result['skipped'],
        ]), 201);
    }

    /** Aportación por cliente miembro de un grupo en un mes (facturas, venta considerada, %, retorno, NC ya generada). */
    public function groupMonthBreakdown(string $id, int $year, int $month)
    {
        try {
            $breakdown = $this->forecastCreditNoteService->getGroupMonthBreakdown($id, $year, $month);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(ApiResponse::error('No encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Aportación por cliente del grupo', $breakdown));
    }

    /** Historial de NC generadas desde forecast para un cliente/grupo. */
    /**
     * Historial global de NC de forecast. El alcance depende del rol: el sales
     * engineer ve su cartera, el manager la de sus ingenieros (o la de uno) y el
     * FORECAST ADMIN todas.
     */
    public function creditNoteHistoryScoped(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $year       = $request->query('year');
        $month      = $request->query('month');
        $engineerId = $request->query('salesEngineerId');
        $entityType = $request->query('tipo');
        $entityId   = $request->query('id');

        try {
            $history = $this->forecastCreditNoteService->getScopedHistory(
                $actor,
                is_numeric($year) ? (int) $year : null,
                is_numeric($engineerId) ? (int) $engineerId : null,
                in_array($entityType, ['cliente', 'grupo'], true) ? $entityType : null,
                is_numeric($entityId) ? (int) $entityId : null,
                is_numeric($month) ? (int) $month : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 403), 403);
        }

        return response()->json(ApiResponse::success('Historial de notas de crédito', ForecastCreditNoteResource::collection($history)));
    }

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

    /** Todos los clientes y grupos con forecast, sin filtrar por sales engineer. `?year=` (default: año actual). */
    public function indexAll(Request $request)
    {
        $year = $request->integer('year') ?: now()->year;

        $result = $this->forecastService->getAll($year);

        return response()->json(ApiResponse::success('Clientes con forecast', $result));
    }

    private const TEMPLATE_MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /** Template CSV para carga masiva de forecast: por sales engineer (?salesEngineerId=) o de todos los clientes. */
    /**
     * Excel de la vista de forecast: por cliente/distribuidor, un renglón de
     * forecast y otro de ventas con los 12 meses y el total. El alcance depende
     * del rol (ver ForecastExportService::resolveScope()).
     */
    public function exportExcel(Request $request)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $year       = (int) $request->query('year', now()->year);
        $engineerId = $request->query('salesEngineerId');
        $engineerId = is_numeric($engineerId) ? (int) $engineerId : null;

        try {
            $export = $this->forecastExportService->build($actor, $year, $engineerId);
        } catch (\RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 403), 403);
        }

        return response($this->excelExportService->buildSheets($export['sheets']), 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function exportTemplate(Request $request)
    {
        $salesEngineerId = $request->query('salesEngineerId');
        $salesEngineerId = is_numeric($salesEngineerId) ? (int) $salesEngineerId : null;

        $clients = $this->forecastService->getExportTemplateClients($salesEngineerId);
        $year    = now()->year;

        $headers = array_merge(['Customer Number', 'Customer Name', 'Is Group', 'Year'], self::TEMPLATE_MONTHS, ['Total Forecast']);

        // El objetivo anual ya cargado viaja de vuelta en la plantilla: subirla sin
        // tocar esa columna no borra el techo del cliente.
        $targets = $this->annualTargets->map(
            ForecastAnnualTarget::TYPE_CLIENT,
            $clients->pluck('idCliente')->all(),
            $year
        );

        $rows = $clients->map(fn (array $client) => array_merge(
            [$client['idCliente'], $client['razonSocial'], $client['isGroup'] ? 'true' : 'false', $year],
            array_fill(0, count(self::TEMPLATE_MONTHS), ''),
            [$targets[(int) $client['idCliente']] ?? '']
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

    public function invoicesByMonth(Request $request, string $idClient, int $year, int $month)
    {
        $currency = $this->requestedCurrency($request);

        if (\App\Models\ClientGroup::where('id', $idClient)->exists()) {
            $data = $this->forecastService->getGroupInvoicesByMonth($idClient, $month, $year, $currency);
            return response()->json(ApiResponse::success('Facturas del mes por grupo', $data));
        }

        $invoices = $this->forecastService->getInvoicesByMonth($idClient, $month, $year, $currency);

        return response()->json(ApiResponse::success('Facturas del mes', $invoices));
    }

    public function invoiceProductsByMonth(Request $request, string $idClient, int $year, int $month)
    {
        $data = $this->forecastService->getInvoiceProductsByMonth($idClient, $month, $year, $this->requestedCurrency($request));

        return response()->json(ApiResponse::success('Productos por factura del mes', $data));
    }

    /**
     * Moneda forzada por el consumidor (?currency=USD). Sin ella, cada cliente se
     * expresa en la moneda que tiene asignada, que es lo que necesita la vista de
     * notas de crédito.
     */
    private function requestedCurrency(Request $request): ?string
    {
        $currency = mb_strtoupper(trim((string) $request->query('currency', '')));

        return in_array($currency, ['USD', 'MXN'], true) ? $currency : null;
    }

    public function exportInvoicesByMonth(Request $request, string $idClient, int $year, int $month)
    {
        $forced = $this->requestedCurrency($request);

        if (\App\Models\ClientGroup::where('id', $idClient)->exists()) {
            $data = $this->forecastService->getGroupInvoicesByMonth($idClient, $month, $year, $forced);

            $sections = collect($data['sections'])->map(function ($section) use ($month, $year) {
                $section['products'] = $this->productsByFolio((string) $section['clientId'], $month, $year, $section['moneda'] ?? null);

                return $section;
            })->all();

            $filename = "facturas_{$data['name']}_{$year}_{$month}.xlsx";

            return Excel::download(
                new ForecastGroupInvoicesExport($sections, $data['name'], $month, $year),
                $filename
            );
        }

        $currency   = $forced ?? $this->forecastService->resolveClientCurrency($idClient);
        $invoices   = $this->forecastService->getInvoicesByMonth($idClient, $month, $year, $currency);
        $clientName = $this->forecastService->getClientName($idClient);

        $filename = "facturas_{$clientName}_{$year}_{$month}.xlsx";

        return Excel::download(
            new ForecastInvoicesExport($invoices, $clientName, $month, $year, null, $this->productsByFolio($idClient, $month, $year, $currency), $idClient, $currency),
            $filename
        );
    }

    /** [folio => Collection de líneas de producto] para el export en Excel. */
    private function productsByFolio(string $idClient, int $month, int $year, ?string $currency = null): \Illuminate\Support\Collection
    {
        return $this->forecastService->getInvoiceProductsByMonth($idClient, $month, $year, $currency)
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
        $data = $request->validated();

        try {
            $saved = $this->forecastService->upsert($data['idClient'], $data['year'], $data['months']);
        } catch (\RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }

        return response()->json(ApiResponse::success('Forecast guardado exitosamente', $saved), 201);
    }

    /** Objetivo anual (techo) de un cliente/grupo o cliente extranjero. */
    public function setAnnualTarget(Request $request, string $tipo, int $id, int $year)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('No autenticado', null, 401), 401);
        }

        if (!$this->roleService->isForecastAdmin($actor) && !$this->roleService->isSalesEngineerManager($actor)) {
            return response()->json(ApiResponse::error('No tienes permiso para modificar el objetivo anual', null, 403), 403);
        }

        $validated = $request->validate([
            'amount' => ['present', 'nullable', 'numeric', 'min:0'],
        ]);

        $type   = $tipo === 'clienteExtranjero'
            ? ForecastAnnualTarget::TYPE_DISTRIBUTOR
            : ForecastAnnualTarget::TYPE_CLIENT;
        $amount = $validated['amount'] === null ? null : (float) $validated['amount'];

        // Un objetivo por debajo de lo ya cargado dejaría la fila permanentemente en rojo.
        $current = $this->annualTargets->currentTotal($type, $id, $year);

        if ($amount !== null && $amount + 0.01 < $current) {
            return response()->json(ApiResponse::error(\sprintf(
                'El objetivo anual (%s) no puede ser menor que el forecast ya cargado (%s).',
                number_format($amount, 2),
                number_format($current, 2)
            ), null, 422), 422);
        }

        $this->annualTargets->set($type, $id, $year, $amount);

        return response()->json(ApiResponse::success('Objetivo anual actualizado', [
            'tipo'         => $tipo,
            'id'           => $id,
            'year'         => $year,
            'annualTarget' => $amount,
            'currentTotal' => $current,
        ]));
    }
}
