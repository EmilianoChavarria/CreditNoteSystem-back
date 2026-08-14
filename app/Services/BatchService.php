<?php

namespace App\Services;

use App\Http\Requests\Batches\StoreBatchRequest;
use App\Jobs\ProcessBatchItemsChunkJob;
use App\Jobs\ProcessBatchJob;
use App\Mail\BatchFinishedMail;
use App\Mail\UserBatchRegisteredMail;
use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\User;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Contracts\BatchTypeHandler;
use App\Services\Batches\Handlers\CreditsDataBatchHandler;
use App\Services\Batches\Handlers\DistributorsBatchHandler;
use App\Services\Batches\Handlers\ForecastBatchHandler;
use App\Services\Batches\Handlers\NationalCustomersBatchHandler;
use App\Services\Batches\Handlers\NewRequestBatchHandler;
use App\Services\Batches\Handlers\OrderNumbersBatchHandler;
use App\Services\Batches\Handlers\ProductClassificationBatchHandler;
use App\Services\Batches\Handlers\SapScreenBatchHandler;
use App\Services\Batches\Handlers\UploadSupportBatchHandler;
use App\Services\Batches\Handlers\UsersBatchHandler;
use App\Services\EmailSenderService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BatchService
{
    /** Filas por INSERT masivo al poblar los items del batch. */
    private const ITEM_INSERT_CHUNK = 500;

    /** Items que procesa un solo job de cola. Evita 1 job por fila. */
    private const ITEM_JOB_CHUNK = 200;

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
        ForecastBatchHandler $forecastBatchHandler,
        ProductClassificationBatchHandler $productClassificationBatchHandler,
        DistributorsBatchHandler $distributorsBatchHandler,
        NationalCustomersBatchHandler $nationalCustomersBatchHandler,
        private readonly NotificationService $notificationService,
        private readonly EmailSenderService $emailSender,
    ) {
        $allHandlers = [
            $sapScreenBatchHandler,
            $creditsDataBatchHandler,
            $orderNumbersBatchHandler,
            $uploadSupportBatchHandler,
            $newRequestBatchHandler,
            $usersBatchHandler,
            $forecastBatchHandler,
            $productClassificationBatchHandler,
            $distributorsBatchHandler,
            $nationalCustomersBatchHandler,
        ];

        foreach ($allHandlers as $handler) {
            $this->handlers[$handler->batchType()] = $handler;
        }
    }

    /**
     * El request HTTP solo guarda el archivo y registra el batch vacío. El parseo y
     * la inserción de items ocurren en ProcessBatchJob: un archivo de cientos de miles
     * de filas revienta el memory_limit y el max_execution_time de PHP-FPM si se hace aquí.
     */
    public function createBatch(StoreBatchRequest $request): Batch
    {
        $batchType = (string) $request->input('batchType');
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            throw new RuntimeException('No se pudo identificar el usuario autenticado.');
        }

        // Falla temprano si el batchType no tiene handler, antes de guardar nada.
        $this->resolveHandler($batchType);

        $storedFiles = $this->storeFiles($request->normalizedFiles(), $batchType);

        $context = new BatchInputContext(
            authUserId: (int) $authUser->id,
            batchType: $batchType,
            requestTypeId: $request->filled('requestTypeId') ? (int) $request->input('requestTypeId') : null,
            minRange: $request->filled('minRange') ? (int) $request->input('minRange') : null,
            maxRange: $request->filled('maxRange') ? (int) $request->input('maxRange') : null,
            storedFiles: $storedFiles,
            userWelcomeEmailMode: $batchType === 'users' ? $request->resolvedWelcomeEmailMode() : 'none',
            userWelcomeEmailRecipient: $batchType === 'users' ? $request->welcomeEmailRecipient() : null,
        );

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

        ProcessBatchJob::dispatch((int) $batch->id, $context->toArray())->onQueue('default');

        return $batch;
    }

    /**
     * Lee las filas del archivo en streaming y las inserta por lotes de 500.
     * Nunca mantiene más de un lote en memoria.
     *
     * @return int total de items insertados
     */
    public function populateBatchItems(int $batchId, BatchInputContext $context): int
    {
        $batch = Batch::find($batchId);
        if (!$batch) {
            throw new RuntimeException('Batch no encontrado: ' . $batchId);
        }

        $handler = $this->resolveHandler($context->batchType);
        $now = Carbon::now();
        $buffer = [];
        $seenRows = 0;

        foreach ($handler->buildRows($context) as $row) {
            if ($context->batchType === 'users') {
                $row = $this->withUserWelcomeEmailOptions($row, $context);
            }

            // Sanitizar datos antes de JSON encoding
            $cleanRow = $this->sanitizeRowForJson($row);
            $jsonEncoded = json_encode($cleanRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($jsonEncoded === false) {
                throw new RuntimeException('Error serializando fila a JSON: ' . json_last_error_msg());
            }

            $buffer[] = [
                'batchId' => $batchId,
                'requestId' => isset($cleanRow['requestId']) ? (int) $cleanRow['requestId'] : null,
                'userId' => $context->authUserId,
                'status' => 'pending',
                'rowHash' => hash('sha256', $jsonEncoded),
                'rawData' => $jsonEncoded,
                'errorLog' => null,
                'processedAt' => null,
                'createdAt' => $now,
            ];

            $seenRows++;

            if (count($buffer) >= self::ITEM_INSERT_CHUNK) {
                BatchItem::insertOrIgnore($buffer);
                $buffer = [];
            }
        }

        if (count($buffer) > 0) {
            BatchItem::insertOrIgnore($buffer);
        }

        if ($seenRows === 0) {
            throw new RuntimeException('No hay registros para procesar en el batch.');
        }

        $totalRecords = BatchItem::where('batchId', $batchId)->count();

        if ($totalRecords === 0) {
            throw new RuntimeException('Todos los registros del archivo están duplicados o inválidos.');
        }

        $batch->update([
            'totalRecords' => $totalRecords,
            'status' => 'processing',
        ]);

        return $totalRecords;
    }

    /**
     * El batch murió antes de generar items (archivo ilegible, formato inválido,
     * cero filas). Se deja rastro como item en error para que el detalle del batch
     * lo muestre igual que cualquier otra falla de fila.
     */
    public function failBatch(int $batchId, Throwable $e): void
    {
        $batch = Batch::find($batchId);
        if (!$batch) {
            return;
        }

        // El parseo puede haber insertado filas antes de reventar; se descartan
        // para no dejar items huérfanos que nadie va a procesar.
        BatchItem::where('batchId', $batchId)->delete();

        BatchItem::insertOrIgnore([[
            'batchId' => $batchId,
            'requestId' => null,
            'userId' => (int) $batch->userId,
            'status' => 'error',
            'rowHash' => hash('sha256', 'batch-failure:' . $batchId),
            'rawData' => json_encode(['_batchFailure' => true], JSON_UNESCAPED_UNICODE),
            'errorLog' => $this->buildErrorLog($e),
            'processedAt' => Carbon::now(),
            'createdAt' => Carbon::now(),
        ]]);

        $batch->update([
            'totalRecords' => 1,
            'processedRecords' => 1,
            'processingRecords' => 0,
            'errorRecords' => 1,
            'status' => 'failed',
        ]);

        $failedBatch = $batch->fresh();

        if ($failedBatch) {
            $this->notifyBatchFinished($failedBatch);
        }
    }

    /**
     * Un job por lote de items, no uno por item: con 200k filas la cola `database`
     * no aguanta 200k jobs (insert + polling + borrado de cada uno).
     */
    public function dispatchBatchItems(int $batchId): void
    {
        BatchItem::query()
            ->select('id')
            ->where('batchId', $batchId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(1000, function ($items) {
                $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

                foreach (array_chunk($ids, self::ITEM_JOB_CHUNK) as $chunk) {
                    ProcessBatchItemsChunkJob::dispatch($chunk)->onQueue('default');
                }
            });
    }

    /**
     * @param array<int, int> $batchItemIds
     */
    public function processBatchItems(array $batchItemIds): void
    {
        foreach ($batchItemIds as $batchItemId) {
            $this->processBatchItem((int) $batchItemId);
        }
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

            Batch::where('id', $item->batchId)
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

        $batchId = (int) $item->batchId;
        $shouldNotifyBatchFinished = false;

        try {
            DB::transaction(function () use ($item, $row, $batchId, &$shouldNotifyBatchFinished) {
                $batch = Batch::query()->lockForUpdate()->find($batchId);
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

                $shouldNotifyBatchFinished = $this->advanceBatchCounters($batchId, false);
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($item, $e, $batchId, &$shouldNotifyBatchFinished) {
                BatchItem::where('id', $item->id)->update([
                    'status' => 'error',
                    'errorLog' => $this->buildErrorLog($e),
                    'processedAt' => now(),
                ]);

                $shouldNotifyBatchFinished = $this->advanceBatchCounters($batchId, true);
            });
        }

        if ($shouldNotifyBatchFinished) {
            $finishedBatch = Batch::query()->find($batchId);

            if ($finishedBatch) {
                $this->notifyBatchFinished($finishedBatch);
                $this->notifyUsersBatchWelcomeEmails($finishedBatch);
            }
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
    private function storeFiles(array $files, string $batchType): array
    {
        $stored = [];

        $targetDisk = 'local';
        $targetBasePath = 'batches/' . now()->format('Y/m/d');

        if ($batchType === 'sapScreen') {
            $targetDisk = (string) Config::get('bulk_upload.sap_screen.disk', 'public');
            $targetBasePath = trim((string) Config::get('bulk_upload.sap_screen.path', 'sap-screen'), '/');
        } elseif ($batchType === 'uploadSupport') {
            $targetDisk = (string) Config::get('bulk_upload.upload_support.disk', 'public');
            $targetBasePath = trim((string) Config::get('bulk_upload.upload_support.path', 'request-support'), '/');
        }

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $targetBasePath . '/' . now()->format('Y/m/d') . '/' . Str::uuid() . '.' . $extension;

            // Se sube por stream para no cargar el archivo completo en memoria.
            $stream = fopen($file->getRealPath(), 'rb');

            try {
                Storage::disk($targetDisk)->put($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $stored[] = [
                'originalName' => $file->getClientOriginalName(),
                'storedPath' => $path,
                'disk' => $targetDisk,
                'extension' => $extension,
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }

    private function advanceBatchCounters(int $batchId, bool $isError): bool
    {
        $batch = Batch::query()->lockForUpdate()->find($batchId);
        if (!$batch) {
            return false;
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

        return $isFinalTransition;
    }

    private function notifyBatchFinished(Batch $batch): void
    {
        $status = (string) $batch->status;

        $this->notificationService->createBatchFinishedNotification($batch);


        $batchWithUser = Batch::query()
            ->with('user:id,fullName,email,preferredLanguage')
            ->find((int) $batch->id);

        $recipientEmail = $batchWithUser?->user?->email;

        if (!$recipientEmail) {
            return;
        }

        try {
            $this->emailSender->send(
                new BatchFinishedMail(
                    batchId: (int) $batch->id,
                    batchType: (string) $batch->batchType,
                    status: $status,
                    totalRecords: (int) $batch->totalRecords,
                    processedRecords: (int) $batch->processedRecords,
                    errorRecords: (int) $batch->errorRecords,
                    processingRecords: (int) $batch->processingRecords,
                    fullName: (string) ($batchWithUser?->user?->fullName ?? 'Usuario'),
                    locale: (string) ($batchWithUser?->user?->preferredLanguage ?? 'es')
                ),
                $recipientEmail
            );
        } catch (Throwable $e) {
            // El batch ya finalizo; si el correo falla solo se registra para diagnostico.
            Log::warning('No se pudo enviar correo de finalizacion de batch', [
                'batchId' => (int) $batch->id,
                'userId' => (int) $batch->userId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withUserWelcomeEmailOptions(array $row, BatchInputContext $context): array
    {
        $password = $this->rowValue($row, ['password'], config('bulk_upload.users.default_password', 'ChangeMe123!'));

        $row['password'] = (string) $password;
        $row['__welcomeEmail'] = [
            'mode' => $context->userWelcomeEmailMode,
            'recipient' => $context->userWelcomeEmailRecipient,
        ];

        return $row;
    }

    private function notifyUsersBatchWelcomeEmails(Batch $batch): void
    {
        if ((string) $batch->batchType !== 'users') {
            return;
        }

        $firstSuccessfulItem = BatchItem::query()
            ->where('batchId', (int) $batch->id)
            ->where('status', 'success')
            ->orderBy('id')
            ->first();

        $firstRawData = is_array($firstSuccessfulItem?->rawData)
            ? $firstSuccessfulItem->rawData
            : (json_decode((string) ($firstSuccessfulItem?->rawData ?? ''), true) ?: []);

        $options = is_array($firstRawData['__welcomeEmail'] ?? null) ? $firstRawData['__welcomeEmail'] : [];
        $mode = (string) ($options['mode'] ?? 'none');
        $recipient = (string) ($options['recipient'] ?? '');

        if ($mode !== 'single' || $recipient === '') {
            return;
        }

        $users = [];

        BatchItem::query()
            ->where('batchId', (int) $batch->id)
            ->where('status', 'success')
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$users) {
                foreach ($items as $item) {
                    $row = is_array($item->rawData)
                        ? $item->rawData
                        : (json_decode((string) $item->rawData, true) ?: []);

                    $email = $this->rowValue($row, ['email']);
                    $fullName = $this->rowValue($row, ['fullname', 'full_name'], (string) $email);
                    $password = $this->rowValue($row, ['password'], (string) config('bulk_upload.users.default_password', 'ChangeMe123!'));
                    $locale = $this->rowValue($row, ['preferredlanguage', 'preferred_language'], 'es');

                    if ($email) {
                        $createdUser = User::query()
                            ->with('role:id,roleName')
                            ->where('email', (string) $email)
                            ->first();

                        $users[] = [
                            'fullName' => (string) $fullName,
                            'email' => (string) $email,
                            'password' => (string) $password,
                            'roleName' => (string) ($createdUser?->role?->roleName ?? ''),
                            'locale' => (string) $locale,
                        ];
                    }
                }
            });

        if (count($users) === 0) {
            return;
        }

        usort($users, function (array $first, array $second) {
            $roleComparison = strcasecmp((string) ($first['roleName'] ?? ''), (string) ($second['roleName'] ?? ''));

            return $roleComparison !== 0
                ? $roleComparison
                : strcasecmp((string) ($first['fullName'] ?? ''), (string) ($second['fullName'] ?? ''));
        });

        try {
            $this->emailSender->send(new UserBatchRegisteredMail($users, (int) $batch->id), $recipient);
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar correo concentrado de usuarios creados por batch', [
                'batchId' => (int) $batch->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function rowValue(array $row, array $aliases, mixed $default = null): mixed
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[strtolower(str_replace(['_', ' ', '-'], '', (string) $key))] = $value;
        }

        foreach ($aliases as $alias) {
            $key = strtolower(str_replace(['_', ' ', '-'], '', $alias));

            if (array_key_exists($key, $normalized)) {
                return $normalized[$key];
            }
        }

        return $default;
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
