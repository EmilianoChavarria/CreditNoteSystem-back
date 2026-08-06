<?php

namespace App\Services;

use App\Models\ClientGroup;
use App\Models\ClientGroupMember;
use App\Models\Distributor;
use App\Models\ForecastChangeRequest;
use App\Models\ForecastComprobante;
use App\Models\ForecastComprobanteProducto;
use App\Models\ForecastSale;
use App\Models\NationalCustomer;
use App\Models\ProductClassification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForecastService
{
    private const CONNECTION       = 'invoices';
    private const CLIENT_TABLE     = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    private const TIPO_NOTA_CREDITO = 'nota de credito';

    public function __construct(
        private readonly BanxicoService $banxico,
        private readonly NationalCustomerService $nationalCustomers,
    ) {}

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
                $q->where('cl.rfc', '!=', 'XEXX010101000');
            })
            // Solo los clientes dados de alta en el padrón participan en forecast.
            ->whereIn('cl.idCliente', $this->nationalCustomers->activeClientIds())
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

        $nationalCustomers = NationalCustomer::whereIn('customerNumber', $clientNumbers)
            ->get()
            ->keyBy('customerNumber');

        $paginator->through(function ($client) use ($distributors, $nationalCustomers) {
            $dist = $distributors->get((string) $client->idCliente);

            if ($dist) {
                $client->razonSocial     = $dist->businessName;
                $client->rfc             = $dist->taxId;
                $client->direccion       = $dist->address;
                $client->correosForecast = $dist->emails;
            }

            $nationalCustomer = $nationalCustomers->get((string) $client->idCliente);

            $client->emails           = $nationalCustomer?->emails;
            $client->returnPercentage = $nationalCustomer?->returnPercentage;
            $client->currency         = $nationalCustomer?->currency;

            return $client;
        });

        return $paginator;
    }

    /** Busca clientes nacionales (RFC real, no el genérico de extranjero/público general) por nombre/número. */
    public function searchClients(string $term): Collection
    {
        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('rfc', '!=', 'XEXX010101000')
            ->whereColumn('idCliente', '!=', 'rfc') // descarta filas fantasma donde idCliente quedó igual al rfc
            ->whereIn('idCliente', $this->nationalCustomers->activeClientIds())
            ->where(function ($q) use ($term) {
                $q->where('razonSocial', 'like', "%{$term}%")
                    ->orWhere('idCliente', 'like', "%{$term}%");
            })
            ->orderBy('razonSocial')
            ->limit(20)
            ->get(['idCliente', 'razonSocial'])
            ->map(fn($row) => [
                'tipo'          => 'cliente',
                'id'            => $row->idCliente,
                'numeroCliente' => $row->idCliente,
                'nombre'        => $row->razonSocial,
            ]);
    }

    /** Busca grupos por nombre, para el autocomplete de forecast. */
    public function searchGroups(string $term): Collection
    {
        return ClientGroup::where('name', 'like', "%{$term}%")
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(fn($group) => [
                'tipo'          => 'grupo',
                'id'            => $group->id,
                'numeroCliente' => $group->id,
                'nombre'        => $group->name,
            ]);
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
        // Groups whose responsible is this sales engineer — always show ALL their members,
        // regardless of each individual member's own salesEngineerId/country in the ext table.
        $myGroups = ClientGroup::where('responsibleUserId', $salesEngineerId)
            ->with('members')
            ->get();

        $myGroupClientIds = $myGroups->flatMap(fn($g) => $g->members->pluck('clientId'))->unique()->values();

        // Clients belonging to ANY group (any responsible) are never listed as individual entries.
        $allGroupedClientIds = ClientGroupMember::pluck('clientId')->unique()->values();

        $extClients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE . ' as cle')
            ->join(self::CLIENT_TABLE . ' as cl', 'cl.idCliente', '=', 'cle.idCliente')
            ->where('cle.salesEngineerId', $salesEngineerId)
            ->where(function ($q) {
                $q->where('cl.rfc', '!=', 'XEXX010101000');
            })
            ->whereIn('cle.idCliente', $this->nationalCustomers->activeClientIds())
            ->select('cle.idCliente', 'cl.razonSocial')
            ->get();

        if ($extClients->isEmpty() && $myGroupClientIds->isEmpty()) {
            return collect();
        }

        $groupClientNames = $myGroupClientIds->isEmpty() ? [] : $this->fetchClientNames($myGroupClientIds->all());

        $allClientIds = $extClients->pluck('idCliente')->merge($myGroupClientIds)->unique()->values()->all();

        $forecastMap     = $this->fetchForecast($allClientIds, $year);
        $modificationMap = $this->fetchModifications($allClientIds, $year);
        $salesMap        = $this->fetchSales($allClientIds, $year);

        $result = collect();

        foreach ($myGroups as $group) {
            $memberIds = $group->members->pluck('clientId')->unique()->values();

            if ($memberIds->isEmpty()) {
                continue;
            }

            $groupClients = $memberIds->map(function ($cid) use ($extClients, $groupClientNames) {
                $known = $extClients->firstWhere('idCliente', $cid);

                return (object) [
                    'idCliente'   => $cid,
                    'razonSocial' => $known->razonSocial ?? ($groupClientNames[$cid] ?? (string) $cid),
                ];
            });

            $result->push($this->buildGroupEntry($group, $groupClients, $year, $salesMap));
        }

        foreach ($extClients as $client) {
            if ($allGroupedClientIds->contains($client->idCliente)) {
                continue; // belongs to a group — shown above (or under another engineer's group)
            }

            $key           = (string) $client->idCliente;
            $forecast      = $forecastMap->get($key, collect());
            $modifications = $modificationMap->get($key, collect());
            $sales         = $salesMap->get($key, collect());

            $months = $forecast->keys()
                ->merge($modifications->keys())
                ->merge($sales->keys())
                ->unique()->sort()->values();

            $result->push([
                'isGroup'     => false,
                'idCliente'   => $client->idCliente,
                'razonSocial' => $client->razonSocial,
                'year'        => $year,
                'months'      => $months->map(fn($m) => $this->buildMonthEntry($m, $forecast, $modifications, $sales))->values(),
            ]);
        }

        return $result;
    }

    /**
     * Lista de clientes/grupos (idCliente + razonSocial + isGroup) para el template de carga masiva de forecast.
     * Los clientes que pertenecen a un grupo no se listan individualmente: el grupo se agrega
     * una sola vez, al final, en su lugar (el forecast se sube contra el grupo, no sus miembros).
     * Si $salesEngineerId es null, retorna todos los clientes/grupos elegibles para forecast.
     *
     * @return Collection<int, array{idCliente: int|string, razonSocial: string|null, isGroup: bool}>
     */
    public function getExportTemplateClients(?int $salesEngineerId): Collection
    {
        return $salesEngineerId !== null
            ? $this->getSalesEngineerClientList($salesEngineerId)
            : $this->getAllForecastClientList();
    }

    /** @return Collection<int, array{idCliente: int|string, razonSocial: string|null, isGroup: bool}> */
    private function getSalesEngineerClientList(int $salesEngineerId): Collection
    {
        $myGroups = ClientGroup::where('responsibleUserId', $salesEngineerId)->get();
        $allGroupedClientIds = ClientGroupMember::pluck('clientId')->unique()->values();

        $extClients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE . ' as cle')
            ->join(self::CLIENT_TABLE . ' as cl', 'cl.idCliente', '=', 'cle.idCliente')
            ->where('cle.salesEngineerId', $salesEngineerId)
            ->where(function ($q) {
                $q->where('cl.rfc', '!=', 'XEXX010101000');
            })
            ->whereIn('cle.idCliente', $this->nationalCustomers->activeClientIds())
            ->select('cle.idCliente', 'cl.razonSocial')
            ->get();

        $result = collect();

        foreach ($extClients as $client) {
            if ($allGroupedClientIds->contains($client->idCliente)) {
                continue; // pertenece a un grupo — se lista como grupo más abajo
            }

            $result->push(['idCliente' => $client->idCliente, 'razonSocial' => $client->razonSocial, 'isGroup' => false]);
        }

        foreach ($myGroups as $group) {
            $result->push(['idCliente' => $group->id, 'razonSocial' => $group->name, 'isGroup' => true]);
        }

        return $result->values();
    }

    /** @return Collection<int, array{idCliente: int|string, razonSocial: string|null, isGroup: bool}> */
    private function getAllForecastClientList(): Collection
    {
        $groupedClientIds = ClientGroupMember::pluck('clientId')->unique()->values()->all();

        $clients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE . ' as cl')
            ->where(function ($q) {
               $q->where('cl.rfc', '!=', 'XEXX010101000');
            })
            ->whereIn('cl.idCliente', $this->nationalCustomers->activeClientIds())
            ->when(!empty($groupedClientIds), fn ($q) => $q->whereNotIn('cl.idCliente', $groupedClientIds))
            ->orderBy('cl.idCliente')
            ->select('cl.idCliente', 'cl.razonSocial')
            ->get()
            ->map(fn($row) => ['idCliente' => $row->idCliente, 'razonSocial' => $row->razonSocial, 'isGroup' => false]);

        $groups = ClientGroup::orderBy('name')
            ->get()
            ->map(fn($group) => ['idCliente' => $group->id, 'razonSocial' => $group->name, 'isGroup' => true]);

        return $clients->concat($groups)->values();
    }

    private function buildGroupEntry(
        ClientGroup $group,
        \Illuminate\Support\Collection $groupClients,
        int $year,
        \Illuminate\Support\Collection $salesMap,
    ): array {
        // Forecast and modifications are stored against the group ID (not individual clients)
        $groupForecast     = $this->fetchForecast([$group->id], $year)->get((string) $group->id, collect());
        $groupModifications = $this->fetchModifications([$group->id], $year)->get((string) $group->id, collect());

        // Each child: only sales per month
        $allSalesMonths = collect();

        $clients = $groupClients->map(function ($client) use ($year, $salesMap, &$allSalesMonths) {
            $sales      = $salesMap->get((string) $client->idCliente, collect());
            $salesMonths = $sales->keys()->sort()->values();

            $allSalesMonths = $allSalesMonths->merge($salesMonths);

            return [
                'idCliente'   => $client->idCliente,
                'razonSocial' => $client->razonSocial,
                'year'        => $year,
                'months'      => $salesMonths->map(fn($m) => [
                    'month' => $m,
                    'sales' => round((float) ($sales->get($m)?->total ?? 0), 2),
                ])->values(),
            ];
        })->values();

        // Build summed sales per month keyed by month — matches what buildMonthEntry expects
        $groupSales = collect();
        foreach ($allSalesMonths->unique() as $month) {
            $total = $groupClients->sum(
                fn($c) => (float) ($salesMap->get((string) $c->idCliente)?->get($month)?->total ?? 0)
            );
            $groupSales->put($month, (object) ['total' => round($total, 2)]);
        }

        $allMonths = $groupForecast->keys()
            ->merge($groupModifications->keys())
            ->merge($groupSales->keys())
            ->unique()->sort()->values();

        $groupMonths = $allMonths->map(
            fn($month) => $this->buildMonthEntry($month, $groupForecast, $groupModifications, $groupSales)
        )->values();

        return [
            'isGroup'     => true,
            'id'          => $group->id,
            'razonSocial' => $group->name,
            'year'        => $year,
            'months'      => $groupMonths,
            'clients'     => $clients,
        ];
    }

    /** Resumen de 12 meses de un cliente nacional: objetivo, venta mensual, %cumplimiento, %retorno (null por ahora). */
    public function getClientSummary(int $idClient, int $year): array
    {
        $forecast = $this->fetchForecast([$idClient], $year)->get((string) $idClient, collect());
        $sales    = $this->fetchSales([$idClient], $year)->get((string) $idClient, collect());

        $returnPercentage = NationalCustomer::where('customerNumber', (string) $idClient)->value('returnPercentage');

        return [
            'numeroCliente' => $idClient,
            'nombre'        => $this->getClientName($idClient),
            'anio'          => $year,
            'meses'         => $this->buildSummaryMonths($forecast, $sales, $returnPercentage !== null ? (float) $returnPercentage : null),
        ];
    }

    /** Resumen de 12 meses de un grupo: objetivo del grupo, venta mensual sumada de sus miembros. */
    public function getGroupSummary(string $groupId, int $year): array
    {
        $group     = ClientGroup::with('members')->findOrFail($groupId);
        $memberIds = $group->members->pluck('clientId')->unique()->values()->all();

        $forecast      = $this->fetchForecast([$groupId], $year)->get((string) $groupId, collect());
        $salesByClient = empty($memberIds) ? collect() : $this->fetchSales($memberIds, $year);

        $sales = collect();
        for ($month = 1; $month <= 12; $month++) {
            $total = collect($memberIds)->sum(
                fn($cid) => (float) ($salesByClient->get((string) $cid)?->get($month)?->total ?? 0)
            );

            if ($total > 0) {
                $sales->put($month, (object) ['total' => round($total, 2)]);
            }
        }

        return [
            'numeroCliente' => $group->id,
            'nombre'        => $group->name,
            'anio'          => $year,
            'meses'         => $this->buildSummaryMonths($forecast, $sales, $group->returnPercentage !== null ? (float) $group->returnPercentage : null),
        ];
    }

    /** Arma los 12 meses de un resumen con objetivo/ventaMensual/%cumplimiento/%retorno. */
    private function buildSummaryMonths(Collection $forecast, Collection $sales, ?float $returnPercentage = null): array
    {
        $meses = [];

        for ($month = 1; $month <= 12; $month++) {
            $objetivo     = $forecast->get($month)?->amount;
            $objetivo     = $objetivo !== null ? (float) $objetivo : null;
            $ventaMensual = $sales->get($month)?->total;
            $ventaMensual = $ventaMensual !== null ? (float) $ventaMensual : null;

            $meses[] = [
                'mes'                    => $month,
                'objetivo'               => $objetivo,
                'ventaMensual'           => $ventaMensual,
                'porcentajeCumplimiento' => ($objetivo > 0 && $ventaMensual !== null)
                    ? round($ventaMensual / $objetivo * 100, 2)
                    : null,
                'porcentajeRetorno'      => $returnPercentage,
            ];
        }

        return $meses;
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
            'deletedAt' => null,
        ], $months);

        // deletedAt se limpia en el update: el índice único (idClient, year, month) ignora
        // el borrado lógico, así que si el cliente se dio de baja y se reactivó, cargarle
        // forecast de nuevo revive la celda con el monto nuevo en vez de chocar.
        ForecastSale::upsert(
            $upserts,
            ['idClient', 'year', 'month'],
            ['amount', 'updatedAt', 'deletedAt']
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
    public function getClientName(int $idClient): string
    {
        $client = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('idCliente', $idClient)
            ->value('razonSocial');

        return $client ?? (string) $idClient;
    }

    public function getGroupInvoicesByMonth(string $groupId, int $month, int $year): array
    {
        $group   = ClientGroup::with('members')->findOrFail($groupId);
        $members = $group->members->unique('clientId')->values();

        $clientNames = $members->isEmpty() ? [] : DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $members->pluck('clientId')->all())
            ->pluck('razonSocial', 'idCliente')
            ->all();

        $sections = $members->map(fn($m) => [
            'clientId'    => $m->clientId,
            'razonSocial' => $clientNames[$m->clientId] ?? (string) $m->clientId,
            'invoices'    => $this->getInvoicesByMonth($m->clientId, $month, $year),
        ])->values()->all();

        return [
            'isGroup'  => true,
            'id'       => $group->id,
            'name'     => $group->name,
            'month'    => $month,
            'year'     => $year,
            'sections' => $sections,
        ];
    }

    public function getInvoicesByMonth(string $idClient, int $month, int $year): Collection
    {
        $invoices = ForecastComprobante::where('receptorId', (string) $idClient)
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->whereMonth('fechaEmision', $month)
            ->orderBy('fechaEmision')
            ->get(['folio', 'subTotal', 'iva', 'total', 'fechaEmision', 'moneda', 'tipoCambio', 'tipoComprobante']);

        if ($invoices->isEmpty()) {
            return $invoices;
        }

        $folios = $invoices->pluck('folio')->all();

        $consideredSubtotalByFolio = $this->consideredSubtotalByFolio((string) $idClient, $folios);
        $devolucionFolios          = $this->devolucionFolios((string) $idClient, $folios);

        $fallbackRate = null;

        return $invoices->filter(function ($invoice) use ($devolucionFolios) {
            // Las notas de crédito que no son devolución no cuentan para la venta:
            // se omiten del desglose para que lo listado sea justo lo que forma el total.
            $rol = $this->comprobanteRol((string) $invoice->tipoComprobante, $devolucionFolios->contains($invoice->folio));

            $invoice->signo = $rol['signo'];

            return $rol['cuenta'];
        })->values()->map(function ($invoice) use (&$fallbackRate, $consideredSubtotalByFolio) {
            $originalSubTotal = (float) $invoice->subTotal;
            $originalTotal    = (float) $invoice->total;
            // Reconstruye subTotal/iva/total desde las líneas de producto (excluyendo No Rodamientos),
            // prorrateando el IVA con el mismo factor total/subTotal de la factura original.
            $factor = $originalSubTotal > 0 ? $originalTotal / $originalSubTotal : 1;

            $subTotal = round($consideredSubtotalByFolio->get($invoice->folio, 0.0), 2);
            $total    = round($subTotal * $factor, 2);
            $iva      = round($total - $subTotal, 2);

            $invoice->subTotal = $subTotal;
            $invoice->iva      = $iva;
            $invoice->total    = $total;

            if ($invoice->moneda === 'MXN') {
                // Use rate stored at sync time; fall back to current rate for legacy rows
                $rate = $invoice->tipoCambio
                    ? (float) $invoice->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());

                $invoice->originalSubTotal = $invoice->subTotal;
                $invoice->originalIva      = $invoice->iva;
                $invoice->originalTotal    = $invoice->total;
                $invoice->originalMoneda   = 'MXN';
                $invoice->tipoCambio       = $rate;

                $invoice->subTotal = round($invoice->subTotal / $rate, 2);
                $invoice->iva      = round($invoice->iva / $rate, 2);
                $invoice->total    = round($invoice->total / $rate, 2);
                $invoice->moneda   = 'USD';
            }
            return $invoice;
        });
    }

    /**
     * true si el PO (noPedido) de una línea corresponde a una devolución de material —
     * siempre empieza con "DM" (ej. DM000005). Ver InvoicePdfService::parseDescripcion().
     */
    private function esDevolucionPo(?string $noPedido): bool
    {
        return $noPedido !== null && str_starts_with(strtoupper(trim($noPedido)), 'DM');
    }

    /**
     * Determina si un comprobante cuenta para la venta mensual y con qué signo:
     *   - Nota de Crédito con PO de devolución → cuenta, resta.
     *   - Nota de Crédito sin PO de devolución → no cuenta; se omite de todos los endpoints.
     *   - Factura (y cualquier otro tipo)      → cuenta, suma.
     *
     * @return array{cuenta: bool, signo: int}
     */
    private function comprobanteRol(string $tipoComprobante, bool $esDevolucion): array
    {
        $tipo = strtolower(trim($tipoComprobante));

        if ($tipo === self::TIPO_NOTA_CREDITO) {
            return $esDevolucion
                ? ['cuenta' => true, 'signo' => -1]
                : ['cuenta' => false, 'signo' => 0];
        }

        return ['cuenta' => true, 'signo' => 1];
    }

    /** Folios de un cliente cuyas líneas traen algún PO de devolución (empieza con "DM"). */
    private function devolucionFolios(string $idClient, array $folios): Collection
    {
        return ForecastComprobanteProducto::where('receptorId', $idClient)
            ->whereIn('folio', $folios)
            ->whereNotNull('noPedido')
            ->get(['folio', 'noPedido'])
            ->filter(fn($p) => $this->esDevolucionPo($p->noPedido))
            ->pluck('folio')
            ->unique()
            ->values();
    }

    /** [folio => suma de importe de sus líneas, excluyendo productos No Rodamientos] */
    private function consideredSubtotalByFolio(string $idClient, array $folios): Collection
    {
        $products = ForecastComprobanteProducto::where('receptorId', $idClient)
            ->whereIn('folio', $folios)
            ->get(['folio', 'noIdentificacion', 'importe']);

        $productIds = $products->map(fn($p) => trim($p->noIdentificacion))->unique()->values()->all();

        $excludedProductIds = ProductClassification::whereIn('idProducto', $productIds)
            ->where('clasificacion', ProductClassification::NO_RODAMIENTOS)
            ->pluck('idProducto')
            ->flip();

        return $products
            ->reject(fn($p) => isset($excludedProductIds[trim($p->noIdentificacion)]))
            ->groupBy('folio')
            ->map(fn($lines) => (float) $lines->sum('importe'));
    }

    /**
     * Desglose de productos por factura de un cliente en un mes/año.
     * Cada línea trae su clasificación (Rodamientos / No Rodamientos / null si no está clasificada);
     * el `breakdown` de cada factura resta lo marcado como No Rodamientos del total facturado.
     */
    public function getInvoiceProductsByMonth(string $idClient, int $month, int $year): Collection
    {
        $invoices = ForecastComprobante::where('receptorId', (string) $idClient)
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->whereMonth('fechaEmision', $month)
            ->orderBy('fechaEmision')
            ->get(['folio', 'subTotal', 'total', 'fechaEmision', 'moneda', 'tipoCambio', 'tipoComprobante']);

        if ($invoices->isEmpty()) {
            return collect();
        }

        $devolucionFolios = $this->devolucionFolios((string) $idClient, $invoices->pluck('folio')->all());

        // Mismo criterio que getInvoicesByMonth(): lo que no cuenta para la venta no se lista.
        $invoices = $invoices->filter(fn($invoice) => $this->comprobanteRol(
            (string) $invoice->tipoComprobante,
            $devolucionFolios->contains($invoice->folio)
        )['cuenta'])->values();

        if ($invoices->isEmpty()) {
            return collect();
        }

        $folios = $invoices->pluck('folio')->all();

        $productsByFolio = ForecastComprobanteProducto::where('receptorId', (string) $idClient)
            ->whereIn('folio', $folios)
            ->orderBy('conceptoIndex')
            ->get(['folio', 'noIdentificacion', 'descripcion', 'cantidad', 'valorUnitario', 'importe'])
            ->groupBy('folio');

        $productIds = $productsByFolio->flatten()
            ->map(fn($p) => trim($p->noIdentificacion))
            ->unique()
            ->values()
            ->all();

        $classifications = ProductClassification::whereIn('idProducto', $productIds)
            ->pluck('clasificacion', 'idProducto');

        $fallbackRate = null;

        return $invoices->map(function ($invoice) use ($productsByFolio, $classifications, &$fallbackRate) {
            $subTotal = (float) $invoice->subTotal;
            $total    = (float) $invoice->total;
            // Las líneas de producto no traen IVA; se prorratea con el mismo factor que fetchSales().
            $factor   = $subTotal > 0 ? $total / $subTotal : 1;

            $rate = null;
            if ($invoice->moneda === 'MXN') {
                $rate = $invoice->tipoCambio
                    ? (float) $invoice->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());
            }

            $lines = $productsByFolio->get($invoice->folio, collect())->map(function ($p) use ($classifications, $factor, $rate) {
                $clasificacion = $classifications[trim($p->noIdentificacion)] ?? null;
                $importeConIva = (float) $p->importe * $factor;
                $importeUsd    = round($rate ? $importeConIva / $rate : $importeConIva, 2);

                return [
                    'noIdentificacion' => $p->noIdentificacion,
                    'descripcion'      => $p->descripcion,
                    'cantidad'         => (float) $p->cantidad,
                    'valorUnitario'    => (float) $p->valorUnitario,
                    'importe'          => round((float) $p->importe, 2),
                    'importeUsd'       => $importeUsd,
                    'clasificacion'    => $clasificacion,
                    'excluido'         => $clasificacion === ProductClassification::NO_RODAMIENTOS,
                ];
            })->values();

            $totalFacturado     = round($lines->sum('importeUsd'), 2);
            $totalNoRodamientos = round($lines->where('excluido', true)->sum('importeUsd'), 2);
            $totalConsiderado   = round($totalFacturado - $totalNoRodamientos, 2);

            return [
                'folio'        => $invoice->folio,
                'fechaEmision' => $invoice->fechaEmision,
                'moneda'       => $rate ? 'USD' : $invoice->moneda,
                'tipoCambio'   => $rate,
                'products'     => $lines,
                'breakdown'    => [
                    'totalFacturado'     => $totalFacturado,
                    'totalNoRodamientos' => $totalNoRodamientos,
                    'totalConsiderado'   => $totalConsiderado,
                ],
            ];
        })->values();
    }

    /** [idCliente => razonSocial] fetched from the external clients table in one query. */
    private function fetchClientNames(array $clientIds): array
    {
        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $clientIds)
            ->pluck('razonSocial', 'idCliente')
            ->all();
    }

    /**
     * Retorna ventas reales (suma de las líneas de producto por factura, en USD) indexado por [idClient][month].
     * Excluye las líneas cuyo producto esté clasificado como No Rodamientos.
     */
    private function fetchSales(array $clientIds, int $year): Collection
    {
        // Solo para comprobantes antiguos que se sincronizaron sin tipoCambio.
        $fallbackRate = $this->banxico->getCurrentUsdRate();
        $receptorIds  = array_map('strval', $clientIds);

        $rows = ForecastComprobanteProducto::query()
            ->join('forecastcomprobantes', function ($join) {
                $join->on('forecastcomprobantes.receptorId', '=', 'forecastcomprobanteproductos.receptorId')
                     ->on('forecastcomprobantes.folio', '=', 'forecastcomprobanteproductos.folio');
            })
            ->whereIn('forecastcomprobanteproductos.receptorId', $receptorIds)
            ->where('forecastcomprobantes.status', 'Emitido')
            ->whereYear('forecastcomprobantes.fechaEmision', $year)
            ->selectRaw('forecastcomprobanteproductos.receptorId as receptorId, forecastcomprobanteproductos.folio as folio, MONTH(forecastcomprobantes.fechaEmision) as month, forecastcomprobanteproductos.noIdentificacion as noIdentificacion, forecastcomprobanteproductos.noPedido as noPedido, forecastcomprobanteproductos.importe as importe, forecastcomprobantes.subTotal as subTotal, forecastcomprobantes.total as total, forecastcomprobantes.moneda as moneda, forecastcomprobantes.tipoCambio as tipoCambio, forecastcomprobantes.tipoComprobante as tipoComprobante')
            ->get();

        $productIds = $rows->map(fn($r) => trim($r->noIdentificacion))->unique()->values()->all();

        $excludedProductIds = ProductClassification::whereIn('idProducto', $productIds)
            ->where('clasificacion', ProductClassification::NO_RODAMIENTOS)
            ->pluck('idProducto')
            ->flip();

        // Por comprobante (receptorId+folio): si cuenta para la venta mensual y con qué signo
        // (Factura suma, Nota de Crédito de devolución resta, cualquier otra Nota de Crédito no cuenta).
        $folioRol = $rows
            ->groupBy(fn($r) => "{$r->receptorId}|{$r->folio}")
            ->map(fn($lines) => $this->comprobanteRol(
                (string) $lines->first()->tipoComprobante,
                $lines->contains(fn($r) => $this->esDevolucionPo($r->noPedido))
            ));

        return $rows
            ->reject(fn($r) => isset($excludedProductIds[trim($r->noIdentificacion)]))
            ->reject(fn($r) => !$folioRol->get("{$r->receptorId}|{$r->folio}")['cuenta'])
            ->groupBy(fn($row) => (string) $row->receptorId)
            ->map(fn($byClient) => $byClient
                ->groupBy('month')
                ->map(function ($rows) use ($fallbackRate, $folioRol) {
                    // Se agrega por comprobante y se redondea igual que getInvoicesByMonth()
                    // para que el total del mes ate al centavo con su desglose.
                    $totalUsd = $rows
                        ->groupBy(fn($r) => "{$r->receptorId}|{$r->folio}")
                        ->sum(function ($lines) use ($fallbackRate, $folioRol) {
                            $first = $lines->first();

                            // El importe de cada línea excluye IVA; se prorratea con el factor
                            // total/subTotal de su comprobante para cuadrar con el total facturado.
                            $factor = (float) $first->subTotal > 0
                                ? (float) $first->total / (float) $first->subTotal
                                : 1;

                            $subTotal = round((float) $lines->sum('importe'), 2);
                            $total    = round($subTotal * $factor, 2);

                            if ($first->moneda === 'MXN') {
                                // Con el tipo de cambio con el que se timbró, no con el actual.
                                $rate  = $first->tipoCambio ? (float) $first->tipoCambio : $fallbackRate;
                                $total = round($total / $rate, 2);
                            }

                            // Las notas de crédito de devolución (signo -1) restan del mes.
                            return $total * $folioRol->get("{$first->receptorId}|{$first->folio}")['signo'];
                        });

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
