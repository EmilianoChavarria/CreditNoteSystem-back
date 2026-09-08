<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Exportación a Excel de la vista de forecast: por cada cliente (o distribuidor
 * extranjero) dos renglones —forecast y ventas— con los 12 meses y su total. La
 * modificación de objetivo no se exporta: solo el objetivo vigente y la venta.
 */
class ForecastExportService
{
    private const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly DistributorForecastService $distributorForecastService,
        private readonly ForecastRoleService $roleService,
        private readonly SalesEngineerAssignmentService $assignmentService,
    ) {
    }

    /**
     * @return array{filename:string, sheets:array<int, array{name:string, headers:array<int,string>, rows:array<int, array<int, mixed>>}>}
     *
     * @throws \RuntimeException cuando el usuario no puede exportar ese alcance.
     */
    public function build(User $actor, int $year, ?int $engineerId): array
    {
        $scope = $this->resolveScope($actor, $engineerId);

        [$clients, $foreign] = $scope['engineerIds'] === null
            ? [$this->forecastService->getAll($year), $this->distributorForecastService->getAll($year)]
            : [
                $this->collectForEngineers($scope['engineerIds'], fn (int $id) => $this->forecastService->getBySalesEngineer($id, $year)),
                $this->collectForEngineers($scope['engineerIds'], fn (int $id) => $this->distributorForecastService->getBySalesEngineer($id, $year)),
            ];

        return [
            'filename' => 'forecast_' . $scope['slug'] . '_' . $year . '_' . now()->format('Ymd_His') . '.xls',
            'sheets'   => [
                $this->sheet('Clientes', $clients),
                $this->sheet('Extranjeros', $foreign),
            ],
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
    private function sheet(string $name, Collection $rows): array
    {
        $headers = array_merge(['Cliente', 'Concepto'], self::MONTHS, ['Total']);

        $sheetRows      = [];
        $totalForecast  = array_fill(1, 12, 0.0);
        $totalSales     = array_fill(1, 12, 0.0);

        foreach ($rows as $row) {
            [$forecast, $sales] = $this->monthlyValues($row);

            $label = ($row['isGroup'] ?? false)
                ? (string) ($row['razonSocial'] ?? '') . ' (Grupo)'
                : trim((string) ($row['idCliente'] ?? '') . ' - ' . (string) ($row['razonSocial'] ?? ''));

            $sheetRows[] = array_merge([$label, 'Forecast'], $this->monthCells($forecast));
            $sheetRows[] = array_merge(['', 'Ventas'], $this->monthCells($sales));

            for ($month = 1; $month <= 12; $month++) {
                $totalForecast[$month] += $forecast[$month];
                $totalSales[$month]    += $sales[$month];
            }
        }

        $sheetRows[] = array_merge(['TOTAL', 'Forecast'], $this->monthCells($totalForecast));
        $sheetRows[] = array_merge(['', 'Ventas'], $this->monthCells($totalSales));

        return ['name' => $name, 'headers' => $headers, 'rows' => $sheetRows];
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
     * @param  array<int, float> $values
     * @return array<int, float> 12 meses + total
     */
    private function monthCells(array $values): array
    {
        $cells = [];

        for ($month = 1; $month <= 12; $month++) {
            $cells[] = round($values[$month] ?? 0, 2);
        }

        $cells[] = round(array_sum($cells), 2);

        return $cells;
    }
}
