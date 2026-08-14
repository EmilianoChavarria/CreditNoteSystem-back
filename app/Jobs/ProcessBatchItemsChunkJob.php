<?php

namespace App\Jobs;

use App\Services\BatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBatchItemsChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param array<int, int> $batchItemIds
     */
    public function __construct(private readonly array $batchItemIds)
    {
    }

    public function handle(BatchService $batchService): void
    {
        $batchService->processBatchItems($this->batchItemIds);
    }
}
