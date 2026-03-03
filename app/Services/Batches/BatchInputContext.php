<?php

namespace App\Services\Batches;

class BatchInputContext
{
    /**
     * @param array<int, array<string, mixed>> $storedFiles
     */
    public function __construct(
        public readonly int $authUserId,
        public readonly string $batchType,
        public readonly ?int $requestTypeId,
        public readonly ?int $minRange,
        public readonly ?int $maxRange,
        public readonly array $storedFiles,
    ) {
    }
}
