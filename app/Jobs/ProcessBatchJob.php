<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $batchId)
    {
    }

    public function handle(BatchService $batchService): void
    {
        $batch = Batch::find($this->batchId);
        if (!$batch || $batch->status !== 'processing') {
            return;
        }

        $batchService->dispatchBatchItems($this->batchId);
    }
}
