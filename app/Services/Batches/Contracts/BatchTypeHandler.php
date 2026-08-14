<?php

namespace App\Services\Batches\Contracts;

use App\Models\Batch;
use App\Services\Batches\BatchInputContext;

interface BatchTypeHandler
{
    public function batchType(): string;

    /**
     * Puede devolver un array o un Generator. Los handlers que leen archivos
     * grandes devuelven Generator para no materializar todas las filas en memoria.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function buildRows(BatchInputContext $context): iterable;

    /**
     * @param array<string, mixed> $row
     */
    public function process(array $row, Batch $batch): ?int;
}
