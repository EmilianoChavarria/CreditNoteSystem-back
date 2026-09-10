<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\ForecastAnnualTarget;
use App\Models\ForecastSale;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\ForecastAnnualTargetService;
use Carbon\Carbon;
use RuntimeException;

class ForecastBatchHandler extends AbstractBatchHandler
{
    /** @var array<int, array<int, string>> */
    private const MONTH_ALIASES = [
        1  => ['january',   'enero',      'jan'],
        2  => ['february',  'febrero',    'feb'],
        3  => ['march',     'marzo',      'mar'],
        4  => ['april',     'abril',      'apr'],
        5  => ['may',       'mayo'],
        6  => ['june',      'junio',      'jun'],
        7  => ['july',      'julio',      'jul'],
        8  => ['august',    'agosto',     'aug'],
        9  => ['september', 'septiembre', 'sep'],
        10 => ['october',   'octubre',    'oct'],
        11 => ['november',  'noviembre',  'nov'],
        12 => ['december',  'diciembre',  'dec'],
    ];

    /** Encabezados aceptados para el objetivo anual (columna "Total Forecast" del template). */
    private const ANNUAL_TARGET_ALIASES = [
        'total_forecast', 'totalforecast', 'total forecast',
        'objetivo_anual', 'objetivoanual', 'objetivo anual',
        'total',
    ];

    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly ForecastAnnualTargetService $annualTargets,
    ) {
    }

    public function batchType(): string
    {
        return 'forecast';
    }

    public function buildRows(BatchInputContext $context): iterable
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para forecast.');
        }

        $parsed = $this->fileParser->parseByStoredFile(
            (string) $file['storedPath'],
            (string) $file['extension']
        );

        foreach ($parsed as $raw) {
            $rowNum   = (int) ($raw['_rowNumber'] ?? 0);
            $idClient = (int) $this->value($raw, ['customer_number', 'customernumber', 'idcliente', 'id_cliente'], 0);
            $year     = (int) $this->value($raw, ['year', 'ano', 'año'], 0);

            if ($idClient <= 0) {
                throw new RuntimeException("Fila {$rowNum}: customer_number es obligatorio y debe ser un entero positivo.");
            }

            if ($year < 2000 || $year > 2100) {
                throw new RuntimeException("Fila {$rowNum}: year '{$year}' inválido.");
            }

            $entry = ['idClient' => $idClient, 'year' => $year];

            $monthsTotal = 0.0;

            foreach (self::MONTH_ALIASES as $monthNum => $aliases) {
                $amount = $this->floatFromMixed($this->value($raw, $aliases, 0));

                $entry['month_' . $monthNum] = $amount;
                $monthsTotal += $amount;
            }

            // Objetivo anual: techo del año. En blanco conserva el que ya tenga cargado.
            $rawTarget = $this->value($raw, self::ANNUAL_TARGET_ALIASES);
            $target    = ($rawTarget === null || trim((string) $rawTarget) === '')
                ? null
                : $this->floatFromMixed($rawTarget);

            if ($target !== null && $target < 0) {
                throw new RuntimeException("Fila {$rowNum}: el objetivo anual no puede ser negativo.");
            }

            if ($target !== null && round($monthsTotal, 2) > $target + 0.01) {
                throw new RuntimeException(\sprintf(
                    'Fila %d: la suma de los 12 meses (%s) supera el objetivo anual (%s).',
                    $rowNum,
                    number_format($monthsTotal, 2),
                    number_format($target, 2)
                ));
            }

            $entry['annualTarget'] = $target;

            yield $entry;
        }
    }

    public function process(array $row, Batch $batch): ?int
    {
        $idClient = (int) ($row['idClient'] ?? 0);
        $year     = (int) ($row['year'] ?? 0);

        if ($idClient <= 0 || $year <= 0) {
            throw new RuntimeException('idClient y year son obligatorios.');
        }

        $now         = Carbon::now();
        $upserts     = [];
        $monthsTotal = 0.0;

        foreach (self::MONTH_ALIASES as $monthNum => $_) {
            $amount       = (float) ($row['month_' . $monthNum] ?? 0);
            $monthsTotal += $amount;

            $upserts[] = [
                'idClient'  => $idClient,
                'year'      => $year,
                'month'     => $monthNum,
                'amount'    => $amount,
                'createdAt' => $now,
                'updatedAt' => $now,
            ];
        }

        // Sin columna de objetivo anual manda el que ya esté cargado: la carga
        // masiva tampoco puede rebasar el techo del cliente.
        $target = array_key_exists('annualTarget', $row) && $row['annualTarget'] !== null
            ? (float) $row['annualTarget']
            : $this->annualTargets->get(ForecastAnnualTarget::TYPE_CLIENT, $idClient, $year);

        if ($target !== null && round($monthsTotal, 2) > $target + 0.01) {
            throw new RuntimeException(\sprintf(
                'La suma de los 12 meses (%s) supera el objetivo anual (%s) del cliente %d.',
                number_format($monthsTotal, 2),
                number_format($target, 2),
                $idClient
            ));
        }

        ForecastSale::upsert(
            $upserts,
            ['idClient', 'year', 'month'],
            ['amount', 'updatedAt']
        );

        if (array_key_exists('annualTarget', $row) && $row['annualTarget'] !== null) {
            $this->annualTargets->set(ForecastAnnualTarget::TYPE_CLIENT, $idClient, $year, (float) $row['annualTarget']);
        }

        return null;
    }
}
