<?php

namespace App\Services;

use App\Events\SocketMessageSent;
use App\Http\Requests\Batches\StoreBatchRequest;
use App\Jobs\ProcessBatchJob;
use App\Models\Batch;
use App\Models\BatchItem;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Contracts\BatchTypeHandler;
use App\Services\Batches\Handlers\CreditsDataBatchHandler;
use App\Services\Batches\Handlers\NewRequestBatchHandler;
use App\Services\Batches\Handlers\OrderNumbersBatchHandler;
use App\Services\Batches\Handlers\SapScreenBatchHandler;
use App\Services\Batches\Handlers\UploadSupportBatchHandler;
use App\Services\Batches\Handlers\UsersBatchHandler;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BatchService
{
    /**
     * @var array<string, BatchTypeHandler>
     */
    private array $handlers;

    public function __construct(
        SapScreenBatchHandler $sapScreenBatchHandler,
        CreditsDataBatchHandler $creditsDataBatchHandler,
        OrderNumbersBatchHandler $orderNumbersBatchHandler,
        UploadSupportBatchHandler $uploadSupportBatchHandler,
        NewRequestBatchHandler $newRequestBatchHandler,
        UsersBatchHandler $usersBatchHandler,
    ) {
        $allHandlers = [
            $sapScreenBatchHandler,
            $creditsDataBatchHandler,
            $orderNumbersBatchHandler,
            $uploadSupportBatchHandler,
            $newRequestBatchHandler,
            $usersBatchHandler,
        ];

        foreach ($allHandlers as $handler) {
            $this->handlers[$handler->batchType()] = $handler;
        }
    }

    public function createBatch(StoreBatchRequest $request): Batch
    {
        $batchType = (string) $request->input('batchType');
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            throw new RuntimeException('No se pudo identificar el usuario autenticado.');
        }

        $storedFiles = $this->storeFiles($request->normalizedFiles());

        $context = new BatchInputContext(
            authUserId: (int) $authUser->id,
            batchType: $batchType,
            requestTypeId: $request->filled('requestTypeId') ? (int) $request->input('requestTypeId') : null,
            minRange: $request->filled('minRange') ? (int) $request->input('minRange') : null,
            maxRange: $request->filled('maxRange') ? (int) $request->input('maxRange') : null,
            storedFiles: $storedFiles,
        );

        $handler = $this->resolveHandler($batchType);
        $rows = $handler->buildRows($context);

        if (count($rows) === 0) {
            throw new RuntimeException('No hay registros para procesar en el batch.');
        }

        $batch = DB::transaction(function () use ($context, $batchType, $rows, $storedFiles) {
            $batch = Batch::create([
                'userId' => $context->authUserId,
                'fileName' => implode(',', array_map(fn ($file) => (string) ($file['originalName'] ?? ''), $storedFiles)),
                'batchType' => $batchType,
                'minRange' => $context->minRange,
                'maxRange' => $context->maxRange,
                'totalRecords' => 0,
                'processedRecords' => 0,
                'processingRecords' => 0,
                'errorRecords' => 0,
                'status' => 'processing',
            ]);

            $insertRows = [];
            $now = Carbon::now();
            foreach ($rows as $row) {
                // Sanitizar datos antes de JSON encoding
                $cleanRow = $this->sanitizeRowForJson($row);
                $jsonEncoded = json_encode($cleanRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if ($jsonEncoded === false) {
                    throw new RuntimeException('Error serializando fila a JSON: ' . json_last_error_msg());
                }

                $rowHash = hash('sha256', $jsonEncoded);

                $insertRows[] = [
                    'batchId' => $batch->id,
                    'requestId' => isset($cleanRow['requestId']) ? (int) $cleanRow['requestId'] : null,
                    'userId' => $context->authUserId,
                    'status' => 'pending',
                    'rowHash' => $rowHash,
                    'rawData' => $jsonEncoded,
                    'errorLog' => null,
                    'processedAt' => null,
                    'createdAt' => $now,
                ];
            }

            foreach (array_chunk($insertRows, 500) as $chunk) {
                DB::table('batchItems')->insertOrIgnore($chunk);
            }

            $totalRecords = BatchItem::where('batchId', $batch->id)->count();
            $batch->update([
                'totalRecords' => $totalRecords,
                'status' => $totalRecords > 0 ? 'processing' : 'failed',
            ]);

            if ($totalRecords === 0) {
                throw new RuntimeException('Todos los registros del archivo están duplicados o inválidos.');
            }

            return $batch->fresh();
        });

        ProcessBatchJob::dispatch($batch->id)->onQueue('default');

        return $batch;
    }

    public function dispatchBatchItems(int $batchId): void
    {
        BatchItem::query()
            ->where('batchId', $batchId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    \App\Jobs\ProcessBatchItemJob::dispatch((int) $item->id)->onQueue('default');
                }
            });
    }

    public function processBatchItem(int $batchItemId): void
    {
        $batchItem = BatchItem::find($batchItemId);
        if (!$batchItem) {
            return;
        }

        if (in_array($batchItem->status, ['success', 'error'], true)) {
            return;
        }

        DB::transaction(function () use ($batchItemId) {
            $item = BatchItem::query()->lockForUpdate()->find($batchItemId);
            if (!$item || $item->status !== 'pending') {
                return;
            }

            $item->update(['status' => 'processing']);

            DB::table('batches')
                ->where('id', $item->batchId)
                ->update([
                    'processingRecords' => DB::raw('processingRecords + 1'),
                ]);
        });

        $item = BatchItem::find($batchItemId);
        if (!$item) {
            return;
        }

        $row = is_array($item->rawData)
            ? $item->rawData
            : (json_decode((string) $item->rawData, true) ?: []);

        try {
            DB::transaction(function () use ($item, $row) {
                $batch = Batch::query()->lockForUpdate()->find($item->batchId);
                if (!$batch) {
                    throw new RuntimeException('Batch no encontrado.');
                }

                $handler = $this->resolveHandler((string) $batch->batchType);
                $requestId = $handler->process($row, $batch);

                $resolvedRequestId = $requestId;
                if ($resolvedRequestId === null && isset($row['requestId']) && is_numeric($row['requestId'])) {
                    $resolvedRequestId = (int) $row['requestId'];
                }

                BatchItem::where('id', $item->id)->update([
                    'requestId' => $resolvedRequestId,
                    'status' => 'success',
                    'errorLog' => null,
                    'processedAt' => now(),
                ]);

                $this->advanceBatchCounters((int) $batch->id, false);
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($item, $e) {
                BatchItem::where('id', $item->id)->update([
                    'status' => 'error',
                    'errorLog' => $this->buildErrorLog($e),
                    'processedAt' => now(),
                ]);

                $this->advanceBatchCounters((int) $item->batchId, true);
            });
        }
    }

    public function resolveHandler(string $batchType): BatchTypeHandler
    {
        if (!isset($this->handlers[$batchType])) {
            throw new RuntimeException('No existe procesador para batchType=' . $batchType);
        }

        return $this->handlers[$batchType];
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array<int, array<string, mixed>>
     */
    private function storeFiles(array $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = 'batches/' . now()->format('Y/m/d') . '/' . Str::uuid() . '.' . $extension;

            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            $stored[] = [
                'originalName' => $file->getClientOriginalName(),
                'storedPath' => $path,
                'extension' => $extension,
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }

    private function advanceBatchCounters(int $batchId, bool $isError): void
    {
        $batch = Batch::query()->lockForUpdate()->find($batchId);
        if (!$batch) {
            return;
        }

        $previousStatus = (string) $batch->status;

        $batch->processedRecords = (int) $batch->processedRecords + 1;
        $batch->processingRecords = max(0, (int) $batch->processingRecords - 1);

        if ($isError) {
            $batch->errorRecords = (int) $batch->errorRecords + 1;
        }

        if ((int) $batch->processedRecords >= (int) $batch->totalRecords) {
            $batch->status = (int) $batch->errorRecords > 0 ? 'failed' : 'completed';
        } else {
            $batch->status = 'processing';
        }

        $batch->save();

        $isFinalTransition =
            !in_array($previousStatus, ['completed', 'failed'], true)
            && in_array((string) $batch->status, ['completed', 'failed'], true);

        if ($isFinalTransition) {
            $this->notifyBatchFinished($batch);
        }
    }

    private function notifyBatchFinished(Batch $batch): void
    {
        $status = (string) $batch->status;
        $isCompleted = $status === 'completed';

        $payload = [
            'title' => $isCompleted ? 'Batch procesado correctamente' : 'Batch procesado con errores',
            'message' => $isCompleted
                ? "El batch #{$batch->id} finalizo exitosamente."
                : "El batch #{$batch->id} finalizo con errores.",
            'type' => $isCompleted ? 'success' : 'error',
            'event' => 'batch.finished',
            'batch' => [
                'id' => (int) $batch->id,
                'batchType' => (string) $batch->batchType,
                'status' => $status,
                'totalRecords' => (int) $batch->totalRecords,
                'processedRecords' => (int) $batch->processedRecords,
                'errorRecords' => (int) $batch->errorRecords,
                'processingRecords' => (int) $batch->processingRecords,
            ],
            'sentAt' => now()->toIso8601String(),
        ];

        broadcast(new SocketMessageSent($payload));
    }

    private function buildErrorLog(Throwable $e): string
    {
        return json_encode([
            'message' => $e->getMessage(),
            'type' => get_class($e),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $e->getMessage();
    }

    /**
     * Sanitizar datos de fila antes de JSON encoding
     * Convierte tipos no JSON-serializables a valores seguros
     */
    private function sanitizeRowForJson(array $row): array
    {
        $sanitized = [];
        
        foreach ($row as $key => $value) {
            if ($value === null) {
                $sanitized[$key] = null;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value ? 1 : 0;
            } elseif (is_int($value) || is_float($value)) {
                $sanitized[$key] = $value;
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->cleanString($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeRowForJson($value);
            } elseif (is_object($value)) {
                // Convertir objetos a array si es posible, sino a string
                if (method_exists($value, 'toArray')) {
                    $sanitized[$key] = $this->sanitizeRowForJson($value->toArray());
                } else {
                    $sanitized[$key] = (string) $value;
                }
            } else {
                // Tipo desconocido: convertir a string
                $sanitized[$key] = (string) $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Limpiar strings para evitar problemas de codificación
     */
    private function cleanString(string $value): string
    {
        // Remover caracteres de control y no-imprimibles que podrían causar problemas JSON
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        // Asegurar UTF-8 válido
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        
        return $value;
    }
}
