<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Services\BatchService;
use App\Services\Batches\BatchInputContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Parsear e insertar cientos de miles de filas puede tardar varios minutos. */
    public int $timeout = 3600;

    /**
     * @param array<string, mixed> $contextPayload
     */
    public function __construct(
        private readonly int $batchId,
        private readonly array $contextPayload = [],
    ) {
    }

    public function handle(BatchService $batchService): void
    {
        $batch = Batch::find($this->batchId);
        if (!$batch || $batch->status !== 'processing') {
            return;
        }

        // Batches creados por la versión anterior ya traen los items insertados.
        if (count($this->contextPayload) > 0) {
            try {
                $batchService->populateBatchItems(
                    $this->batchId,
                    BatchInputContext::fromArray($this->contextPayload)
                );
            } catch (Throwable $e) {
                Log::error('No se pudieron generar los items del batch', [
                    'batchId' => $this->batchId,
                    'error' => $e->getMessage(),
                ]);

                $batchService->failBatch($this->batchId, $e);

                return;
            }
        }

        $batchService->dispatchBatchItems($this->batchId);
    }

    public function failed(Throwable $e): void
    {
        app(BatchService::class)->failBatch($this->batchId, $e);
    }
}
