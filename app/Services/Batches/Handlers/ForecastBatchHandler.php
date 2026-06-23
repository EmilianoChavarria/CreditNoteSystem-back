<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\ForecastSale;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
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

    public function __construct(
        private readonly BulkFileParser $fileParser,
    ) {
    }

    public function batchType(): string
    {
        return 'forecast';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para forecast.');
        }

        $parsed = $this->fileParser->parseByStoredFile(
            (string) $file['storedPath'],
            (string) $file['extension']
        );

        $rows = [];

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

            foreach (self::MONTH_ALIASES as $monthNum => $aliases) {
                $entry['month_' . $monthNum] = $this->floatFromMixed($this->value($raw, $aliases, 0));
            }

            $rows[] = $entry;
        }

        return $rows;
    }

    public function process(array $row, Batch $batch): ?int
    {
        $idClient = (int) ($row['idClient'] ?? 0);
        $year     = (int) ($row['year'] ?? 0);

        if ($idClient <= 0 || $year <= 0) {
            throw new RuntimeException('idClient y year son obligatorios.');
        }

        $now     = Carbon::now();
        $upserts = [];

        foreach (self::MONTH_ALIASES as $monthNum => $_) {
            $upserts[] = [
                'idClient'  => $idClient,
                'year'      => $year,
                'month'     => $monthNum,
                'amount'    => (float) ($row['month_' . $monthNum] ?? 0),
                'createdAt' => $now,
                'updatedAt' => $now,
            ];
        }

        ForecastSale::upsert(
            $upserts,
            ['idClient', 'year', 'month'],
            ['amount', 'updatedAt']
        );

        return null;
    }
}
