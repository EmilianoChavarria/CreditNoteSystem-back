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
use App\Models\User;
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

    /** Moneda por defecto del programa forecast, cuando el cliente no tiene una asignada. */
    public const DEFAULT_CURRENCY = 'USD';

    /** Únicas monedas entre las que se convierte con el FIX del comprobante. */
    private const CONVERTIBLE_CURRENCIES = ['USD', 'MXN'];

    /** Clientes por lote al calcular ventas del año: acota el pico de memoria. Ver fetchSales(). */
    private const SALES_CHUNK_SIZE = 10;

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
                // El nombre SAP vive en national_customers (otra conexión): se
                // resuelven los ids que casan y se suman al filtro.
                $sapMatches = $this->nationalCustomers->numbersMatchingSapName($search);

                $q->where(function ($sub) use ($search, $sapMatches) {
                    $sub->where('cl.razonSocial', 'like', "%{$search}%")
                        ->orWhere('cl.rfc', 'like', "%{$search}%")
                        ->orWhere('cl.idCliente', 'like', "%{$search}%");

                    if (!empty($sapMatches)) {
                        $sub->orWhereIn('cl.idCliente', $sapMatches);
                    }
                });
            })
            ->orderBy('cl.idCliente')
            ->select([
                'cl.idCliente',
                'cl.razonSocial',
                'cl.direccion',
                'cl.rfc',
                'cle.correosForecast',
                'cle.salesEngineerId',
                'cle.salesManagerId',
            ])
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

        // users vive en la conexion default y clientes_ext en 'invoices': el nombre
        // del responsable se resuelve en PHP, no con join.
        $responsibleIds = collect($paginator->items())
            ->flatMap(fn ($client) => [$client->salesEngineerId ?? null, $client->salesManagerId ?? null])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $responsibleNames = count($responsibleIds) > 0
            ? User::whereIn('id', $responsibleIds)->pluck('fullName', 'id')
            : collect();

        $sapNames = $this->nationalCustomers->sapNamesFor($clientNumbers);

        $paginator->through(function ($client) use ($distributors, $nationalCustomers, $responsibleNames, $sapNames) {
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

            $client->sapName = $sapNames[(string) $client->idCliente] ?? null;

            $client->salesEngineerId   = $client->salesEngineerId !== null ? (int) $client->salesEngineerId : null;
            $client->salesManagerId    = $client->salesManagerId !== null ? (int) $client->salesManagerId : null;
            $client->salesEngineerName = $responsibleNames[$client->salesEngineerId] ?? null;
            $client->salesManagerName  = $responsibleNames[$client->salesManagerId] ?? null;

            return $client;
        });

        return $paginator;
    }

    /** Busca clientes nacionales (RFC real, no el genérico de extranjero/público general) por nombre/número. */
    public function searchClients(string $term): Collection
    {
        $sapMatches = $this->nationalCustomers->numbersMatchingSapName($term);

        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('rfc', '!=', 'XEXX010101000')
            ->whereColumn('idCliente', '!=', 'rfc') // descarta filas fantasma donde idCliente quedó igual al rfc
            ->whereIn('idCliente', $this->nationalCustomers->activeClientIds())
            ->where(function ($q) use ($term, $sapMatches) {
                $q->where('razonSocial', 'like', "%{$term}%")
                    ->orWhere('idCliente', 'like', "%{$term}%");

                if (!empty($sapMatches)) {
                    $q->orWhereIn('idCliente', $sapMatches);
                }
            })
            ->orderBy('razonSocial')
            ->limit(20)
            ->get(['idCliente', 'razonSocial'])
            ->map(fn($row) => [
                'tipo'          => 'cliente',
                'id'            => $row->idCliente,
                'numeroCliente' => $row->idCliente,
                'nombre'        => $this->displayName((string) $row->idCliente, (string) $row->razonSocial),
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

    /** Moneda asignada al cliente nacional en el padrón (USD si no tiene). */
    public function resolveClientCurrency(string $clientId): string
    {
        return $this->nationalCustomers->currencyFor($clientId);
    }

    /**
     * Moneda de un grupo: la de sus miembros cuando todos comparten la misma.
     * Con monedas mixtas cae a USD, la única en la que los totales del grupo son
     * comparables entre sí (cada NC se sigue generando en la moneda de su cliente).
     */
    public function resolveGroupCurrency(string $groupId): string
    {
        $memberIds = ClientGroupMember::where('groupId', $groupId)->pluck('clientId')->all();

        if (empty($memberIds)) {
            return self::DEFAULT_CURRENCY;
        }

        $currencies = array_unique(array_values($this->nationalCustomers->currenciesFor($memberIds)));

        return count($currencies) === 1 ? $currencies[0] : self::DEFAULT_CURRENCY;
    }

    /**
     * Convierte entre USD y MXN con el tipo de cambio del comprobante (FIX del día de
     * emisión, MXN por USD). Devuelve null si el par no es convertible: el importe se
     * deja tal cual y conserva su moneda original.
     */
    private function convertAmount(float $amount, string $from, string $to, ?float $rate): ?float
    {
        if ($from === $to) {
            return $amount;
        }

        if (
            $rate === null || $rate <= 0
            || !in_array($from, self::CONVERTIBLE_CURRENCIES, true)
            || !in_array($to, self::CONVERTIBLE_CURRENCIES, true)
        ) {
            return null;
        }

        return $to === self::DEFAULT_CURRENCY ? $amount / $rate : $amount * $rate;
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

        return $this->buildForecastRows($myGroups, $this->withSapNames($extClients), $year);
    }

    /**
     * Todos los grupos y todos los clientes del padrón, sin filtrar por sales engineer.
     * Mismo formato que getBySalesEngineer() para que el frontend reutilice la tabla.
     */
    public function getAll(int $year): Collection
    {
        $groups = ClientGroup::with('members')->orderBy('name')->get();

        $clients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE . ' as cl')
            ->where(function ($q) {
                $q->where('cl.rfc', '!=', 'XEXX010101000');
            })
            ->whereIn('cl.idCliente', $this->nationalCustomers->activeClientIds())
            ->orderBy('cl.razonSocial')
            ->select('cl.idCliente', 'cl.razonSocial')
            ->get();

        return $this->buildForecastRows($groups, $this->withSapNames($clients), $year);
    }

    /**
     * Sustituye la razón social por el nombre SAP en los candidatos a fila.
     *
     * @param  Collection<int, object> $clients
     * @return Collection<int, object>
     */
    private function withSapNames(Collection $clients): Collection
    {
        if ($clients->isEmpty()) {
            return $clients;
        }

        $sapNames = $this->nationalCustomers->sapNamesFor($clients->pluck('idCliente')->all());

        return $clients->each(function ($client) use ($sapNames): void {
            $client->razonSocial = $sapNames[(string) $client->idCliente] ?? $client->razonSocial;
        });
    }

    /**
     * Arma las filas de forecast: primero un renglón por grupo (con sus miembros) y
     * después los clientes que no pertenecen a ninguno — un cliente agrupado nunca se
     * lista aparte, su forecast vive en el grupo.
     *
     * @param Collection<int, ClientGroup> $groups
     * @param Collection<int, object> $clients Candidatos a fila individual (idCliente + razonSocial).
     */
    private function buildForecastRows(Collection $groups, Collection $clients, int $year): Collection
    {
        $groupClientIds = $groups->flatMap(fn($g) => $g->members->pluck('clientId'))->unique()->values();

        if ($clients->isEmpty() && $groupClientIds->isEmpty()) {
            return collect();
        }

        // Clients belonging to ANY group (any responsible) are never listed as individual entries.
        $allGroupedClientIds = ClientGroupMember::pluck('clientId')->unique()->values();

        $groupClientNames = $groupClientIds->isEmpty() ? [] : $this->fetchClientNames($groupClientIds->all());

        $allClientIds = $clients->pluck('idCliente')->merge($groupClientIds)->unique()->values()->all();

        $forecastMap     = $this->fetchForecast($allClientIds, $year);
        $modificationMap = $this->fetchModifications($allClientIds, $year);
        $salesMap        = $this->fetchSales($allClientIds, $year);

        $result = collect();

        foreach ($groups as $group) {
            $memberIds = $group->members->pluck('clientId')->unique()->values();

            if ($memberIds->isEmpty()) {
                continue;
            }

            $groupClients = $memberIds->map(function ($cid) use ($clients, $groupClientNames) {
                $known = $clients->firstWhere('idCliente', $cid);

                return (object) [
                    'idCliente'   => $cid,
                    'razonSocial' => $known->razonSocial ?? ($groupClientNames[$cid] ?? (string) $cid),
                ];
            });

            $result->push($this->buildGroupEntry($group, $groupClients, $year, $salesMap));
        }

        foreach ($clients as $client) {
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
        $currency = $this->resolveClientCurrency((string) $idClient);

        $forecast = $this->fetchForecast([$idClient], $year)->get((string) $idClient, collect());
        $sales    = $this->fetchSales([$idClient], $year, $currency)->get((string) $idClient, collect());

        $returnPercentage = NationalCustomer::where('customerNumber', (string) $idClient)->value('returnPercentage');

        return [
            'numeroCliente' => $idClient,
            'nombre'        => $this->getClientName($idClient),
            'anio'          => $year,
            'moneda'        => $currency,
            'meses'         => $this->buildSummaryMonths($forecast, $sales, $returnPercentage !== null ? (float) $returnPercentage : null, $currency),
        ];
    }

    /** Resumen de 12 meses de un grupo: objetivo del grupo, venta mensual sumada de sus miembros. */
    public function getGroupSummary(string $groupId, int $year): array
    {
        $group     = ClientGroup::with('members')->findOrFail($groupId);
        $memberIds = $group->members->pluck('clientId')->unique()->values()->all();

        $currency = $this->resolveGroupCurrency($groupId);

        $forecast      = $this->fetchForecast([$groupId], $year)->get((string) $groupId, collect());
        $salesByClient = empty($memberIds) ? collect() : $this->fetchSales($memberIds, $year, $currency);

        $sales = collect();
        for ($month = 1; $month <= 12; $month++) {
            $total = collect($memberIds)->sum(
                fn($cid) => (float) ($salesByClient->get((string) $cid)?->get($month)?->total ?? 0)
            );

            $totalCurrency = collect($memberIds)->sum(
                fn($cid) => (float) ($salesByClient->get((string) $cid)?->get($month)?->totalCurrency ?? 0)
            );

            if ($total > 0) {
                $sales->put($month, (object) [
                    'total'         => round($total, 2),
                    'totalCurrency' => round($totalCurrency, 2),
                ]);
            }
        }

        return [
            'numeroCliente' => $group->id,
            'nombre'        => $group->name,
            'anio'          => $year,
            'moneda'        => $currency,
            'meses'         => $this->buildSummaryMonths($forecast, $sales, $group->returnPercentage !== null ? (float) $group->returnPercentage : null, $currency),
        ];
    }

    /**
     * Arma los 12 meses de un resumen con objetivo/ventaMensual/%cumplimiento/%retorno.
     * `ventaMensual` va en USD (el objetivo está en USD, así que el cumplimiento se mide ahí);
     * `ventaMensualMoneda` es la misma venta en la moneda del cliente/grupo, base del retorno.
     */
    private function buildSummaryMonths(
        Collection $forecast,
        Collection $sales,
        ?float $returnPercentage,
        string $currency
    ): array {
        $meses = [];

        for ($month = 1; $month <= 12; $month++) {
            $objetivo     = $forecast->get($month)?->amount;
            $objetivo     = $objetivo !== null ? (float) $objetivo : null;
            $ventaMensual = $sales->get($month)?->total;
            $ventaMensual = $ventaMensual !== null ? (float) $ventaMensual : null;
            $ventaMoneda  = $sales->get($month)?->totalCurrency;
            $ventaMoneda  = $ventaMoneda !== null ? (float) $ventaMoneda : null;

            $meses[] = [
                'mes'                    => $month,
                'objetivo'               => $objetivo,
                'ventaMensual'           => $ventaMensual,
                'ventaMensualMoneda'     => $ventaMoneda,
                'moneda'                 => $currency,
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

    public function getGroupInvoicesByMonth(string $groupId, int $month, int $year, ?string $currency = null): array
    {
        $group   = ClientGroup::with('members')->findOrFail($groupId);
        $members = $group->members->unique('clientId')->values();

        $clientNames = $members->isEmpty() ? [] : DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $members->pluck('clientId')->all())
            ->pluck('razonSocial', 'idCliente')
            ->all();

        // Sin moneda forzada, cada sección va en la moneda de su propio cliente:
        // es la que manda en su nota de crédito.
        $sections = $members->map(function ($m) use ($month, $year, $currency, $clientNames) {
            $target = $currency ?? $this->resolveClientCurrency((string) $m->clientId);

            return [
                'clientId'    => $m->clientId,
                'razonSocial' => $clientNames[$m->clientId] ?? (string) $m->clientId,
                'moneda'      => $target,
                'invoices'    => $this->getInvoicesByMonth($m->clientId, $month, $year, $target),
            ];
        })->values()->all();

        return [
            'isGroup'  => true,
            'id'       => $group->id,
            'name'     => $group->name,
            'month'    => $month,
            'year'     => $year,
            'sections' => $sections,
        ];
    }

    /** Facturas de un cliente en un mes, expresadas en la moneda que tiene asignada (por defecto la suya). */
    public function getInvoicesByMonth(string $idClient, int $month, int $year, ?string $currency = null): Collection
    {
        $target = $currency ?? $this->resolveClientCurrency((string) $idClient);

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
        })->values()->map(function ($invoice) use (&$fallbackRate, $consideredSubtotalByFolio, $target) {
            $originalSubTotal = (float) $invoice->subTotal;
            $originalTotal    = (float) $invoice->total;
            // Reconstruye subTotal/iva/total desde las líneas de producto (solo Rodamientos),
            // prorrateando el IVA con el mismo factor total/subTotal de la factura original.
            $factor = $originalSubTotal > 0 ? $originalTotal / $originalSubTotal : 1;

            $subTotal = round($consideredSubtotalByFolio->get($invoice->folio, 0.0), 2);
            $total    = round($subTotal * $factor, 2);
            $iva      = round($total - $subTotal, 2);

            $invoice->subTotal = $subTotal;
            $invoice->iva      = $iva;
            $invoice->total    = $total;

            $moneda = (string) $invoice->moneda;

            if ($moneda !== $target) {
                // Use rate stored at sync time; fall back to current rate for legacy rows
                $rate = $invoice->tipoCambio
                    ? (float) $invoice->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());

                $convertedSubTotal = $this->convertAmount((float) $invoice->subTotal, $moneda, $target, $rate);

                if ($convertedSubTotal !== null) {
                    $invoice->originalSubTotal = $invoice->subTotal;
                    $invoice->originalIva      = $invoice->iva;
                    $invoice->originalTotal    = $invoice->total;
                    $invoice->originalMoneda   = $moneda;
                    $invoice->tipoCambio       = $rate;

                    $invoice->subTotal = round($convertedSubTotal, 2);
                    $invoice->iva      = round((float) $this->convertAmount((float) $invoice->iva, $moneda, $target, $rate), 2);
                    $invoice->total    = round((float) $this->convertAmount((float) $invoice->total, $moneda, $target, $rate), 2);
                    $invoice->moneda   = $target;
                }
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

    /**
     * Ids de producto que sí cuentan para la venta: solo los clasificados como Rodamientos.
     * Lo No Rodamientos y lo que aún no tiene clasificación se resta del total facturado.
     *
     * @param array<int, string> $productIds ids ya trimeados
     * @return Collection ids incluidos como claves (flip), para lookup O(1)
     */
    private function includedProductIds(array $productIds): Collection
    {
        return ProductClassification::whereIn('idProducto', $productIds)
            ->where('clasificacion', ProductClassification::RODAMIENTOS)
            ->pluck('idProducto')
            ->flip();
    }

    /** [folio => suma de importe de sus líneas, contando solo productos Rodamientos] */
    private function consideredSubtotalByFolio(string $idClient, array $folios): Collection
    {
        $products = ForecastComprobanteProducto::where('receptorId', $idClient)
            ->whereIn('folio', $folios)
            ->get(['folio', 'noIdentificacion', 'importe']);

        $productIds = $products->map(fn($p) => trim($p->noIdentificacion))->unique()->values()->all();

        $includedProductIds = $this->includedProductIds($productIds);

        return $products
            ->reject(fn($p) => !isset($includedProductIds[trim($p->noIdentificacion)]))
            ->groupBy('folio')
            ->map(fn($lines) => (float) $lines->sum('importe'));
    }

    /**
     * Desglose de productos por factura de un cliente en un mes/año.
     * Cada línea trae su clasificación (Rodamientos / No Rodamientos / null si no está clasificada);
     * el `breakdown` de cada factura resta del total facturado todo lo que no es Rodamientos,
     * incluidos los productos sin clasificar.
     * Los importes convertidos van en la moneda asignada al cliente (por defecto la suya).
     */
    public function getInvoiceProductsByMonth(string $idClient, int $month, int $year, ?string $currency = null): Collection
    {
        $target = $currency ?? $this->resolveClientCurrency((string) $idClient);

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

        $roles = $invoices->mapWithKeys(fn($invoice) => [
            (string) $invoice->folio => $this->comprobanteRol(
                (string) $invoice->tipoComprobante,
                $devolucionFolios->contains($invoice->folio)
            ),
        ]);

        // Mismo criterio que getInvoicesByMonth(): lo que no cuenta para la venta no se lista.
        $invoices = $invoices->filter(fn($invoice) => $roles[(string) $invoice->folio]['cuenta'])->values();

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

        return $invoices->map(function ($invoice) use ($productsByFolio, $classifications, &$fallbackRate, $target, $roles) {
            $subTotal = (float) $invoice->subTotal;
            $total    = (float) $invoice->total;
            // Las líneas de producto no traen IVA; se prorratea con el mismo factor que fetchSales().
            $factor   = $subTotal > 0 ? $total / $subTotal : 1;

            $moneda = (string) $invoice->moneda;
            $rate   = null;

            if ($moneda !== $target) {
                $candidate = $invoice->tipoCambio
                    ? (float) $invoice->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());

                // Solo se marca como convertida si el par de monedas sí es convertible.
                $rate = $this->convertAmount(1.0, $moneda, $target, $candidate) !== null ? $candidate : null;
            }

            $lines = $productsByFolio->get($invoice->folio, collect())->map(function ($p) use ($classifications, $factor, $rate, $moneda, $target) {
                $clasificacion = $classifications[trim($p->noIdentificacion)] ?? null;
                $importeConIva = (float) $p->importe * $factor;
                $convertido    = $rate ? (float) $this->convertAmount($importeConIva, $moneda, $target, $rate) : $importeConIva;

                return [
                    'noIdentificacion'  => $p->noIdentificacion,
                    'descripcion'       => $p->descripcion,
                    'cantidad'          => (float) $p->cantidad,
                    'valorUnitario'     => (float) $p->valorUnitario,
                    'importe'           => round((float) $p->importe, 2),
                    'importeConvertido' => round($convertido, 2),
                    'clasificacion'     => $clasificacion,
                    'excluido'          => $clasificacion !== ProductClassification::RODAMIENTOS,
                ];
            })->values();

            $totalFacturado   = round($lines->sum('importeConvertido'), 2);
            $totalExcluido    = round($lines->where('excluido', true)->sum('importeConvertido'), 2);
            $totalConsiderado = round($totalFacturado - $totalExcluido, 2);

            return [
                'folio'        => $invoice->folio,
                'fechaEmision' => $invoice->fechaEmision,
                'moneda'       => $rate ? $target : $moneda,
                'tipoCambio'   => $rate,
                // 1 = factura (suma), -1 = nota de crédito de devolución (resta del total del mes).
                'signo'        => $roles[(string) $invoice->folio]['signo'],
                'products'     => $lines,
                'breakdown'    => [
                    'totalFacturado'   => $totalFacturado,
                    'totalExcluido'    => $totalExcluido,
                    'totalConsiderado' => $totalConsiderado,
                ],
            ];
        })->values();
    }

    /** [idCliente => razonSocial] fetched from the external clients table in one query. */
    private function fetchClientNames(array $clientIds): array
    {
        $names = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $clientIds)
            ->pluck('razonSocial', 'idCliente')
            ->all();

        return $this->applySapNames($names);
    }

    /**
     * En forecast el cliente se identifica por su nombre SAP; la razón social
     * queda como respaldo cuando no tiene uno cargado.
     *
     * @param  array<int|string, string> $names [idCliente => razonSocial]
     * @return array<int|string, string>
     */
    private function applySapNames(array $names): array
    {
        if (empty($names)) {
            return $names;
        }

        $sapNames = $this->nationalCustomers->sapNamesFor(array_keys($names));

        foreach ($names as $id => $name) {
            $names[$id] = $sapNames[(string) $id] ?? $name;
        }

        return $names;
    }

    /** Nombre con el que se muestra un cliente dentro de forecast. */
    private function displayName(string $idCliente, string $razonSocial): string
    {
        return $this->nationalCustomers->sapNamesFor([$idCliente])[$idCliente] ?? $razonSocial;
    }

    /**
     * Retorna ventas reales (suma de las líneas de producto por factura) indexado por [idClient][month].
     * Cada mes trae `total` en USD —con el que se mide el cumplimiento contra el objetivo— y
     * `totalCurrency`, el mismo importe en $currency, que es sobre el que se calcula el retorno.
     * Solo cuentan las líneas cuyo producto está clasificado como Rodamientos: No Rodamientos
     * y los productos aún sin clasificar se restan del total facturado.
     */
    private function fetchSales(array $clientIds, int $year, string $currency = self::DEFAULT_CURRENCY): Collection
    {
        // Solo para comprobantes antiguos que se sincronizaron sin tipoCambio.
        $fallbackRate = $this->banxico->getCurrentUsdRate();
        $receptorIds  = array_map('strval', array_values(array_unique($clientIds)));

        // Se procesa por lotes de clientes: el detalle de líneas de un año completo para todo
        // el padrón no cabe en el memory_limit de PHP-FPM. Lo que sobrevive a cada lote es
        // solo el agregado por cliente/mes, que es diminuto.
        return collect(array_chunk($receptorIds, self::SALES_CHUNK_SIZE))
            ->reduce(
                fn (Collection $carry, array $chunk) => $carry->union($this->fetchSalesChunk($chunk, $year, $currency, $fallbackRate)),
                collect()
            );
    }

    /** Un lote de clientes de fetchSales(). Ver su docblock. */
    private function fetchSalesChunk(array $receptorIds, int $year, string $currency, float $fallbackRate): Collection
    {
        $rows = ForecastComprobanteProducto::query()
            ->join('forecastcomprobantes', function ($join) {
                $join->on('forecastcomprobantes.receptorId', '=', 'forecastcomprobanteproductos.receptorId')
                     ->on('forecastcomprobantes.folio', '=', 'forecastcomprobanteproductos.folio');
            })
            ->whereIn('forecastcomprobanteproductos.receptorId', $receptorIds)
            ->where('forecastcomprobantes.status', 'Emitido')
            // Rango en vez de whereYear() para que el índice de fechaEmision sí se use.
            ->whereBetween('forecastcomprobantes.fechaEmision', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
            ->selectRaw('forecastcomprobanteproductos.receptorId as receptorId, forecastcomprobanteproductos.folio as folio, MONTH(forecastcomprobantes.fechaEmision) as month, forecastcomprobanteproductos.noIdentificacion as noIdentificacion, forecastcomprobanteproductos.noPedido as noPedido, forecastcomprobanteproductos.importe as importe, forecastcomprobantes.subTotal as subTotal, forecastcomprobantes.total as total, forecastcomprobantes.moneda as moneda, forecastcomprobantes.tipoCambio as tipoCambio, forecastcomprobantes.tipoComprobante as tipoComprobante')
            // toBase(): filas planas en vez de modelos Eloquent. Ninguno de los dos modelos usa
            // scopes globales, y hidratar decenas de miles de modelos es lo que reventaba la memoria.
            ->toBase()
            ->get();

        $productIds = $rows->map(fn($r) => trim($r->noIdentificacion))->unique()->values()->all();

        $includedProductIds = $this->includedProductIds($productIds);

        // Por comprobante (receptorId+folio): si cuenta para la venta mensual y con qué signo
        // (Factura suma, Nota de Crédito de devolución resta, cualquier otra Nota de Crédito no cuenta).
        $folioRol = $rows
            ->groupBy(fn($r) => "{$r->receptorId}|{$r->folio}")
            ->map(fn($lines) => $this->comprobanteRol(
                (string) $lines->first()->tipoComprobante,
                $lines->contains(fn($r) => $this->esDevolucionPo($r->noPedido))
            ));

        return $rows
            ->reject(fn($r) => !isset($includedProductIds[trim($r->noIdentificacion)]))
            ->reject(fn($r) => !$folioRol->get("{$r->receptorId}|{$r->folio}")['cuenta'])
            ->groupBy(fn($row) => (string) $row->receptorId)
            ->map(fn($byClient) => $byClient
                ->groupBy('month')
                ->map(function ($rows) use ($fallbackRate, $folioRol, $currency) {
                    // Se agrega por comprobante y se redondea igual que getInvoicesByMonth()
                    // para que el total del mes ate al centavo con su desglose.
                    $totals = $rows
                        ->groupBy(fn($r) => "{$r->receptorId}|{$r->folio}")
                        ->reduce(function (array $carry, $lines) use ($fallbackRate, $folioRol, $currency) {
                            $first = $lines->first();

                            // El importe de cada línea excluye IVA; se prorratea con el factor
                            // total/subTotal de su comprobante para cuadrar con el total facturado.
                            $factor = (float) $first->subTotal > 0
                                ? (float) $first->total / (float) $first->subTotal
                                : 1;

                            $subTotal = round((float) $lines->sum('importe'), 2);
                            $total    = round($subTotal * $factor, 2);
                            $moneda   = (string) $first->moneda;

                            // Con el tipo de cambio con el que se timbró, no con el actual.
                            $rate = $first->tipoCambio ? (float) $first->tipoCambio : $fallbackRate;

                            // Las notas de crédito de devolución (signo -1) restan del mes.
                            $signo = $folioRol->get("{$first->receptorId}|{$first->folio}")['signo'];

                            $carry['usd'] += round($this->convertAmount($total, $moneda, self::DEFAULT_CURRENCY, $rate) ?? $total, 2) * $signo;
                            $carry['currency'] += round($this->convertAmount($total, $moneda, $currency, $rate) ?? $total, 2) * $signo;

                            return $carry;
                        }, ['usd' => 0.0, 'currency' => 0.0]);

                    return (object) [
                        'total'         => round($totals['usd'], 2),
                        'totalCurrency' => round($totals['currency'], 2),
                    ];
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
