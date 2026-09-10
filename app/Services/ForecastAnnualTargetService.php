<?php

namespace App\Services;

use App\Models\DistributorForecast;
use App\Models\ForecastAnnualTarget;
use App\Models\ForecastSale;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Objetivo anual de forecast: el techo que la suma de los 12 meses no puede rebasar.
 *
 * Se guarda por (tipo, id, año) en forecastannualtargets. El tipo 'cliente' comparte
 * el espacio de ids de forecastsales.idClient —clientes nacionales y grupos—, y
 * 'clienteExtranjero' apunta a distributors.id.
 */
class ForecastAnnualTargetService
{
    /** Tolerancia al comparar contra el objetivo: evita falsos positivos por centavos. */
    private const EPSILON = 0.01;

    /**
     * Objetivos anuales de varios ids en un año: [targetId => amount].
     *
     * @param  array<int, int|string> $ids
     * @return array<int, float>
     */
    public function map(string $type, array $ids, int $year): array
    {
        $this->assertType($type);

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return [];
        }

        return ForecastAnnualTarget::where('targetType', $type)
            ->where('year', $year)
            ->whereIn('targetId', $ids)
            ->pluck('amount', 'targetId')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    /** Objetivo anual de un id, o null si no tiene uno cargado. */
    public function get(string $type, int|string $id, int $year): ?float
    {
        $this->assertType($type);

        $amount = ForecastAnnualTarget::where('targetType', $type)
            ->where('targetId', (int) $id)
            ->where('year', $year)
            ->value('amount');

        return $amount === null ? null : (float) $amount;
    }

    /** Guarda el objetivo anual. Con $amount null se elimina (el cliente queda sin techo). */
    public function set(string $type, int|string $id, int $year, ?float $amount): void
    {
        $this->assertType($type);

        if ($amount === null) {
            ForecastAnnualTarget::where('targetType', $type)
                ->where('targetId', (int) $id)
                ->where('year', $year)
                ->delete();

            return;
        }

        ForecastAnnualTarget::updateOrCreate(
            ['targetType' => $type, 'targetId' => (int) $id, 'year' => $year],
            ['amount' => round($amount, 2), 'updatedAt' => Carbon::now()]
        );
    }

    /**
     * Verifica que los meses propuestos no hagan que el año rebase el objetivo anual.
     * Los meses que no van en $proposedByMonth conservan su valor guardado.
     *
     * @param  array<int, float> $proposedByMonth [mes (1-12) => monto propuesto]
     * @return array{target: float, projected: float, message: string}|null null si no se rebasa
     *         (o si el cliente no tiene objetivo anual cargado).
     */
    public function check(string $type, int|string $id, int $year, array $proposedByMonth): ?array
    {
        $target = $this->get($type, $id, $year);

        if ($target === null) {
            return null;
        }

        $months = $this->storedMonths($type, $id, $year);

        foreach ($proposedByMonth as $month => $amount) {
            $months[(int) $month] = (float) $amount;
        }

        $projected = round(array_sum($months), 2);

        if ($projected <= $target + self::EPSILON) {
            return null;
        }

        return [
            'target'    => $target,
            'projected' => $projected,
            'message'   => \sprintf(
                'La suma de los 12 meses (%s) supera el objetivo anual de %s (%s). Ajusta el objetivo anual o reduce los meses.',
                number_format($projected, 2),
                $year,
                number_format($target, 2)
            ),
        ];
    }

    /**
     * Suma guardada de los 12 meses, sin propuestas.
     */
    public function currentTotal(string $type, int|string $id, int $year): float
    {
        return round(array_sum($this->storedMonths($type, $id, $year)), 2);
    }

    /**
     * Forecast guardado del año: [mes => monto].
     *
     * @return array<int, float>
     */
    private function storedMonths(string $type, int|string $id, int $year): array
    {
        $this->assertType($type);

        if ($type === ForecastAnnualTarget::TYPE_DISTRIBUTOR) {
            return DistributorForecast::where('distributorId', (int) $id)
                ->where('year', $year)
                ->pluck('forecast', 'month')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        return ForecastSale::where('idClient', (int) $id)
            ->where('year', $year)
            ->pluck('amount', 'month')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function assertType(string $type): void
    {
        if (!\in_array($type, ForecastAnnualTarget::TYPES, true)) {
            throw new InvalidArgumentException("Tipo de objetivo anual inválido: {$type}");
        }
    }
}
