<?php

namespace App\Services;

use App\Models\ClientGroup;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exportación a Excel de la vista de forecast: un renglón por cliente (o distribuidor
 * extranjero) con su nombre, su ingeniero de ventas y, para cada mes, el forecast y
 * la venta en columnas contiguas; al final el objetivo anual. La modificación de
 * objetivo no se exporta: solo el objetivo vigente y la venta.
 */
class ForecastExportService
{
    private const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    private const CONNECTION       = 'invoices';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly DistributorForecastService $distributorForecastService,
        private readonly ForecastRoleService $roleService,
        private readonly SalesEngineerAssignmentService $assignmentService,
    ) {
    }

    /** Alcances válidos del parámetro `tipo`; null exporta las dos hojas. */
    public const TIPO_NACIONALES  = 'nacionales';
    public const TIPO_EXTRANJEROS = 'extranjeros';

    /**
     * @param  string|null $tipo 'nacionales', 'extranjeros' o null para ambas hojas.
     * @return array{filename:string, sheets:array<int, array{name:string, headers:array<int,string>, rows:array<int, array<int, mixed>>}>}
     *
     * @throws \RuntimeException cuando el usuario no puede exportar ese alcance.
     */
    public function build(User $actor, int $year, ?int $engineerId, ?string $tipo = null): array
    {
        $scope = $this->resolveScope($actor, $engineerId);

        $sheets = [];

        if ($tipo !== self::TIPO_EXTRANJEROS) {
            $clients  = $scope['engineerIds'] === null
                ? $this->forecastService->getAll($year)
                : $this->collectForEngineers($scope['engineerIds'], fn (int $id) => $this->forecastService->getBySalesEngineer($id, $year));

            $sheets[] = $this->sheet('Clientes', $clients, $this->clientEngineerNames($clients));
        }

        if ($tipo !== self::TIPO_NACIONALES) {
            $foreign  = $scope['engineerIds'] === null
                ? $this->distributorForecastService->getAll($year)
                : $this->collectForEngineers($scope['engineerIds'], fn (int $id) => $this->distributorForecastService->getBySalesEngineer($id, $year));

            $sheets[] = $this->sheet('Extranjeros', $foreign, $this->distributorEngineerNames($foreign));
        }

        $prefix = match ($tipo) {
            self::TIPO_NACIONALES  => 'forecast_nacionales_',
            self::TIPO_EXTRANJEROS => 'forecast_extranjeros_',
            default                => 'forecast_',
        };

        return [
            'filename' => $prefix . $scope['slug'] . '_' . $year . '_' . now()->format('Ymd_His') . '.xls',
            'sheets'   => $sheets,
        ];
    }

    /**
     * Ingenieros cuyo forecast puede exportar el usuario.
     *
     * @return array{engineerIds: array<int, int>|null, slug: string} engineerIds null = todos
     */
    private function resolveScope(User $actor, ?int $engineerId): array
    {
        // FORECAST ADMIN: cualquier ingeniero, o todo el padrón.
        if ($this->roleService->isForecastAdmin($actor)) {
            return $engineerId
                ? ['engineerIds' => [$engineerId], 'slug' => 'se' . $engineerId]
                : ['engineerIds' => null, 'slug' => 'all'];
        }

        // SALES ENGINEER / MANAGER: solo los ingenieros que tiene a su cargo.
        if ($this->roleService->isSalesEngineerManager($actor)) {
            $assigned = $this->assignmentService->getAssignedUsers($actor)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($engineerId === null) {
                return ['engineerIds' => $assigned, 'slug' => 'my_engineers'];
            }

            if (!in_array($engineerId, $assigned, true)) {
                throw new \RuntimeException('El ingeniero de ventas seleccionado no está a tu cargo.');
            }

            return ['engineerIds' => [$engineerId], 'slug' => 'se' . $engineerId];
        }

        // SALES ENGINEER: siempre su propia cartera, sin importar lo que pida.
        return ['engineerIds' => [(int) $actor->id], 'slug' => 'se' . $actor->id];
    }

    /**
     * Une el forecast de varios ingenieros sin repetir clientes (un grupo puede
     * aparecer para más de un ingeniero).
     *
     * @param  array<int, int> $engineerIds
     * @return Collection<int, array>
     */
    private function collectForEngineers(array $engineerIds, callable $fetch): Collection
    {
        $rows = collect();

        foreach ($engineerIds as $id) {
            $rows = $rows->merge($fetch($id));
        }

        return $rows
            ->unique(fn (array $row) => ($row['isGroup'] ?? false)
                ? 'g' . ($row['id'] ?? '')
                : 'c' . ($row['idCliente'] ?? ''))
            ->values();
    }

    /**
     * @param  Collection<int, array> $rows
     * @return array{name:string, headers:array<int,string>, rows:array<int, array<int, mixed>>}
     */
    /**
     * @param  Collection<int, array> $rows
     * @param  array<string, string>  $engineerNames Ingeniero por clave de fila (ver rowKey()).
     * @return array{name:string, headers:array<int,string>, rows:array<int, array<int, mixed>>}
     */
    private function sheet(string $name, Collection $rows, array $engineerNames = []): array
    {
        // Un renglón por cliente: nombre, ingeniero y, por cada mes, forecast y venta juntos.
        $headers = ['Cliente', 'Sales Engineer'];

        foreach (self::MONTHS as $month) {
            $headers[] = $month . ' Forecast';
            $headers[] = $month . ' Ventas';
        }

        $headers[] = 'Total Forecast';
        $headers[] = 'Total Ventas';
        $headers[] = 'Objetivo anual';

        $sheetRows     = [];
        $totalForecast = array_fill(1, 12, 0.0);
        $totalSales    = array_fill(1, 12, 0.0);
        $totalTarget   = 0.0;

        foreach ($rows as $row) {
            [$forecast, $sales] = $this->monthlyValues($row);

            // razonSocial ya trae el nombre SAP cuando el cliente tiene uno cargado.
            $label = ($row['isGroup'] ?? false)
                ? (string) ($row['razonSocial'] ?? '') . ' (Grupo)'
                : ((string) ($row['razonSocial'] ?? '') ?: (string) ($row['idCliente'] ?? ''));

            $target       = $row['annualTarget'] ?? null;
            $totalTarget += (float) ($target ?? 0);

            $sheetRows[] = array_merge(
                [$label, $engineerNames[$this->rowKey($row)] ?? ''],
                $this->monthCells($forecast, $sales),
                // Sin objetivo cargado la celda va vacía, no en cero: no es lo mismo.
                [$target === null ? '' : round((float) $target, 2)]
            );

            for ($month = 1; $month <= 12; $month++) {
                $totalForecast[$month] += $forecast[$month];
                $totalSales[$month]    += $sales[$month];
            }
        }

        $sheetRows[] = array_merge(
            ['TOTAL', ''],
            $this->monthCells($totalForecast, $totalSales),
            [round($totalTarget, 2)]
        );

        return ['name' => $name, 'headers' => $headers, 'rows' => $sheetRows];
    }

    /** Clave con la que se indexa una fila: distingue grupos de clientes. */
    private function rowKey(array $row): string
    {
        return ($row['isGroup'] ?? false)
            ? 'g' . ($row['id'] ?? '')
            : 'c' . ($row['idCliente'] ?? '');
    }

    /**
     * Ingeniero de ventas de cada fila de la hoja de clientes: el del padrón para
     * los clientes y el responsable del grupo para los grupos.
     *
     * @param  Collection<int, array> $rows
     * @return array<string, string>
     */
    private function clientEngineerNames(Collection $rows): array
    {
        $clientIds = [];
        $groupIds  = [];

        foreach ($rows as $row) {
            if ($row['isGroup'] ?? false) {
                $groupIds[] = (int) ($row['id'] ?? 0);
            } else {
                $clientIds[] = (string) ($row['idCliente'] ?? '');
            }
        }

        $engineerByClient = empty($clientIds) ? [] : DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE)
            ->whereIn('idCliente', $clientIds)
            ->whereNotNull('salesEngineerId')
            ->pluck('salesEngineerId', 'idCliente')
            ->all();

        $engineerByGroup = empty($groupIds) ? [] : ClientGroup::whereIn('id', $groupIds)
            ->whereNotNull('responsibleUserId')
            ->pluck('responsibleUserId', 'id')
            ->all();

        $names = $this->userNames(array_merge(array_values($engineerByClient), array_values($engineerByGroup)));

        $result = [];

        foreach ($engineerByClient as $clientId => $userId) {
            $result['c' . $clientId] = $names[(int) $userId] ?? '';
        }

        foreach ($engineerByGroup as $groupId => $userId) {
            $result['g' . $groupId] = $names[(int) $userId] ?? '';
        }

        return $result;
    }

    /**
     * @param  Collection<int, array> $rows
     * @return array<string, string>
     */
    private function distributorEngineerNames(Collection $rows): array
    {
        $ids = $rows->map(fn (array $row) => (int) ($row['idCliente'] ?? 0))->filter()->values()->all();

        if (empty($ids)) {
            return [];
        }

        $engineerByDistributor = Distributor::whereIn('id', $ids)
            ->whereNotNull('salesEngineerId')
            ->pluck('salesEngineerId', 'id')
            ->all();

        $names  = $this->userNames(array_values($engineerByDistributor));
        $result = [];

        foreach ($engineerByDistributor as $distributorId => $userId) {
            $result['c' . $distributorId] = $names[(int) $userId] ?? '';
        }

        return $result;
    }

    /**
     * @param  array<int, int|string|null> $ids
     * @return array<int, string> [userId => fullName]
     */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->pluck('fullName', 'id')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    /**
     * Forecast y ventas por mes (1-12), con 0 en los meses sin dato. Los clientes
     * usan `amount`; los distribuidores extranjeros, `forecast`.
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private function monthlyValues(array $row): array
    {
        $forecast = array_fill(1, 12, 0.0);
        $sales    = array_fill(1, 12, 0.0);

        foreach ($row['months'] ?? [] as $month) {
            $index = (int) ($month['month'] ?? 0);

            if ($index < 1 || $index > 12) {
                continue;
            }

            $forecast[$index] = (float) ($month['amount'] ?? $month['forecast'] ?? 0);
            $sales[$index]    = (float) ($month['sales'] ?? 0);
        }

        return [$forecast, $sales];
    }

    /**
     * Celdas de un renglón: forecast y venta de cada mes intercalados y, al final,
     * el total de cada concepto.
     *
     * @param  array<int, float> $forecast
     * @param  array<int, float> $sales
     * @return array<int, float> 12 pares + 2 totales
     */
    private function monthCells(array $forecast, array $sales): array
    {
        $cells         = [];
        $forecastTotal = 0.0;
        $salesTotal    = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $monthForecast = round($forecast[$month] ?? 0, 2);
            $monthSales    = round($sales[$month] ?? 0, 2);

            $cells[] = $monthForecast;
            $cells[] = $monthSales;

            $forecastTotal += $monthForecast;
            $salesTotal    += $monthSales;
        }

        $cells[] = round($forecastTotal, 2);
        $cells[] = round($salesTotal, 2);

        return $cells;
    }
}
