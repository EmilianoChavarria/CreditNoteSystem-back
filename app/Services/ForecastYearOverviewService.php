<?php

namespace App\Services;

/**
 * Arma el panorama de los 12 meses de un cliente/grupo/distribuidor para los correos
 * de forecast: cada mes con su objetivo vigente y, en los que se tocaron, el monto
 * anterior y el propuesto para poder resaltarlos.
 */
class ForecastYearOverviewService
{
    private const MONTH_NAMES = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function __construct(
        private readonly ForecastAnnualTargetService $annualTargets,
    ) {
    }

    /**
     * @param  array<int, array{month?: int, year?: int, monthLabel?: string, previousAmount?: float, proposedAmount?: float}> $changes
     *         Meses tocados en esta operación. Los que no traen 'month' se ignoran, y los
     *         que traen un 'year' distinto al del panorama tampoco entran: un lote puede
     *         resolver meses de varios años y cada correo muestra un solo año.
     * @return array{
     *     year: int,
     *     months: array<int, array{month:int, monthLabel:string, amount:float, changed:bool, previousAmount:?float, proposedAmount:?float}>,
     *     total: float,
     *     changedTotal: float,
     *     annualTarget: ?float,
     *     exceedsTarget: bool
     * }
     */
    public function build(string $type, int|string $id, int $year, array $changes): array
    {
        $stored = $this->annualTargets->storedMonths($type, $id, $year);

        $changeByMonth = [];
        foreach ($changes as $change) {
            if (!isset($change['month'])) {
                continue;
            }

            if (isset($change['year']) && (int) $change['year'] !== $year) {
                continue;
            }

            $changeByMonth[(int) $change['month']] = $change;
        }

        $months       = [];
        $total        = 0.0;
        $changedTotal = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $change  = $changeByMonth[$month] ?? null;
            // En una solicitud pendiente el monto guardado sigue siendo el anterior:
            // el propuesto manda para que el correo muestre a dónde va el mes.
            $amount  = $change !== null && isset($change['proposedAmount'])
                ? (float) $change['proposedAmount']
                : (float) ($stored[$month] ?? 0);

            $months[] = [
                'month'          => $month,
                'monthLabel'     => $this->monthName($month),
                'amount'         => $amount,
                'changed'        => $change !== null,
                'previousAmount' => $change !== null && isset($change['previousAmount']) ? (float) $change['previousAmount'] : null,
                'proposedAmount' => $change !== null && isset($change['proposedAmount']) ? (float) $change['proposedAmount'] : null,
            ];

            $total += $amount;

            if ($change !== null) {
                $changedTotal += $amount;
            }
        }

        $annualTarget = $this->annualTargets->get($type, $id, $year);

        return [
            'year'          => $year,
            'months'        => $months,
            'total'         => round($total, 2),
            'changedTotal'  => round($changedTotal, 2),
            'annualTarget'  => $annualTarget,
            'exceedsTarget' => $annualTarget !== null && round($total, 2) > $annualTarget + 0.01,
        ];
    }

    private function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month - 1] ?? (string) $month;
    }
}
