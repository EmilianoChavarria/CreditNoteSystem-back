<?php

namespace App\Services\Batches\Contracts;

use App\Models\Batch;
use App\Services\Batches\BatchInputContext;

interface BatchTypeHandler
{
    public function batchType(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildRows(BatchInputContext $context): array;

    /**
     * @param array<string, mixed> $row
     */
    public function process(array $row, Batch $batch): void;
}
