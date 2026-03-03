<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Batches\StoreBatchRequest;
use App\Models\Batch;
use App\Models\BatchItem;
use App\Services\BatchService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BatchController extends Controller
{
    public function store(StoreBatchRequest $request, BatchService $batchService)
    {
        try {
            $batch = $batchService->createBatch($request);

            return response()->json(ApiResponse::success('Batch creado y encolado', [
                'id' => $batch->id,
                'batchType' => $batch->batchType,
                'status' => $batch->status,
                'totalRecords' => $batch->totalRecords,
                'processedRecords' => $batch->processedRecords,
                'processingRecords' => $batch->processingRecords,
                'errorRecords' => $batch->errorRecords,
                'createdAt' => $batch->createdAt,
            ], 201), 201);
        } catch (Throwable $e) {
            return response()->json(ApiResponse::error('No se pudo crear el batch', [
                'message' => $e->getMessage(),
            ], 422), 422);
        }
    }

    public function show(int $id, Request $request)
    {
        $batch = Batch::find($id);

        if (!$batch) {
            return response()->json(ApiResponse::error('Batch no encontrado', null, 404), 404);
        }

        $statusCounters = BatchItem::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->where('batchId', $batch->id)
            ->groupBy('status')
            ->pluck('total', 'status');

        $perPage = max(1, min(200, (int) $request->query('perPage', 25)));

        $errorItems = BatchItem::query()
            ->where('batchId', $batch->id)
            ->where('status', 'error')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function (BatchItem $item) {
                $rawData = is_array($item->rawData)
                    ? $item->rawData
                    : (json_decode((string) $item->rawData, true) ?: []);

                // errorLog ya es array debido al cast en el modelo
                $errorLog = is_array($item->errorLog) ? $item->errorLog : null;

                return [
                    'id' => $item->id,
                    'rowHash' => $item->rowHash,
                    'requestId' => $item->requestId,
                    'userId' => $item->userId,
                    'status' => $item->status,
                    'processedAt' => $item->processedAt,
                    'errorLog' => $errorLog ?: ['message' => 'Sin detalles de error'],
                    'rawData' => $rawData,
                ];
            });

        $total = max(1, (int) $batch->totalRecords);
        $progressPercent = round(((int) $batch->processedRecords / $total) * 100, 2);

        return response()->json(ApiResponse::success('Estado del batch', [
            'batch' => [
                'id' => $batch->id,
                'userId' => $batch->userId,
                'fileName' => $batch->fileName,
                'batchType' => $batch->batchType,
                'minRange' => $batch->minRange,
                'maxRange' => $batch->maxRange,
                'status' => $batch->status,
                'totalRecords' => $batch->totalRecords,
                'processedRecords' => $batch->processedRecords,
                'processingRecords' => $batch->processingRecords,
                'errorRecords' => $batch->errorRecords,
                'createdAt' => $batch->createdAt,
                'progressPercent' => $progressPercent,
            ],
            'itemsSummary' => [
                'pending' => (int) ($statusCounters['pending'] ?? 0),
                'processing' => (int) ($statusCounters['processing'] ?? 0),
                'success' => (int) ($statusCounters['success'] ?? 0),
                'error' => (int) ($statusCounters['error'] ?? 0),
            ],
            'errors' => [
                'data' => $errorItems->items(),
                'pagination' => [
                    'currentPage' => $errorItems->currentPage(),
                    'perPage' => $errorItems->perPage(),
                    'total' => $errorItems->total(),
                    'lastPage' => $errorItems->lastPage(),
                ],
            ],
        ]));
    }
}
