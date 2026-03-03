<?php

namespace App\Jobs;

use App\Services\BatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBatchItemJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $batchItemId)
    {
    }

    public function handle(BatchService $batchService): void
    {
        $batchService->processBatchItem($this->batchItemId);
    }
}
