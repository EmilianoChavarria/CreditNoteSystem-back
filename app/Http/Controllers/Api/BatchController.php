<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Batches\StoreBatchRequest;
use App\Http\Resources\BatchDetailResource;
use App\Http\Resources\BatchItemResource;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Models\BatchItem;
use App\Services\BatchService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = max(1, min(200, (int) $request->query('perPage', 15)));

        $batches = Batch::query()
            ->where('userId', (int) $authUser->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $batches->setCollection(BatchResource::collection($batches->getCollection())->collection);

        return response()->json(ApiResponse::success('Batches', $batches));
    }

    public function productClassificationBatches(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = max(1, min(200, (int) $request->query('perPage', 15)));

        $batches = Batch::query()
            ->where('userId', (int) $authUser->id)
            ->where('batchType', 'productClassification')
            ->orderByDesc('id')
            ->paginate($perPage);

        $batches->setCollection(BatchResource::collection($batches->getCollection())->collection);

        return response()->json(ApiResponse::success('Cargas de clasificación de productos', $batches));
    }

    public function distributorBatches(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = max(1, min(200, (int) $request->query('perPage', 15)));

        $batches = Batch::query()
            ->where('userId', (int) $authUser->id)
            ->where('batchType', 'distributors')
            ->orderByDesc('id')
            ->paginate($perPage);

        $batches->setCollection(BatchResource::collection($batches->getCollection())->collection);

        return response()->json(ApiResponse::success('Cargas de distribuidores', $batches));
    }

    public function nationalCustomersBatches(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = max(1, min(200, (int) $request->query('perPage', 15)));

        $batches = Batch::query()
            ->where('userId', (int) $authUser->id)
            ->where('batchType', 'nationalCustomers')
            ->orderByDesc('id')
            ->paginate($perPage);

        $batches->setCollection(BatchResource::collection($batches->getCollection())->collection);

        return response()->json(ApiResponse::success('Cargas de clientes nacionales', $batches));
    }

    public function forecastBatches(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = max(1, min(200, (int) $request->query('perPage', 15)));

        $batches = Batch::query()
            ->where('userId', (int) $authUser->id)
            ->where('batchType', 'forecast')
            ->orderByDesc('id')
            ->paginate($perPage);

        $batches->setCollection(BatchResource::collection($batches->getCollection())->collection);

        return response()->json(ApiResponse::success('Cargas de forecast', $batches));
    }

    public function store(StoreBatchRequest $request, BatchService $batchService)
    {
        try {
            $batch = $batchService->createBatch($request);

            return response()->json(ApiResponse::success('Batch creado y encolado', BatchResource::make($batch), 201), 201);
        } catch (Throwable $e) {
            Log::error('Error emitiendo evento request.assigned', [
                'error' => $e->getMessage(),
            ]);
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
            ->paginate($perPage);

        $errorItems->setCollection(BatchItemResource::collection($errorItems->getCollection())->collection);

        return response()->json(ApiResponse::success('Estado del batch', [
            'batch' => BatchDetailResource::make($batch),
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

    public function requests(int $id, Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $batch = Batch::query()
            ->where('id', $id)
            ->where('userId', (int) $authUser->id)
            ->first();

        if (!$batch) {
            return response()->json(ApiResponse::error('Batch no encontrado', null, 404), 404);
        }

        $perPage = max(1, min(200, (int) $request->query('perPage', 25)));
        $status = (string) $request->query('status', 'all');

        $items = BatchItem::query()
            ->with(['request.requestType', 'request.reason', 'request.classification'])
            ->where('batchId', $batch->id)
            ->when($status === 'error', fn ($query) => $query->where('status', 'error'))
            // "success" agrupa todo lo que no quedó en error (incluye pending/processing).
            ->when($status === 'success', fn ($query) => $query->where('status', '!=', 'error'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items->setCollection(BatchItemResource::collection($items->getCollection())->collection);

        return response()->json(ApiResponse::success('Solicitudes del batch', [
            'batch' => BatchResource::make($batch),
            'items' => $items,
        ]));
    }

    public function errorsCsv(int $id, Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $batch = Batch::query()
            ->where('id', $id)
            ->where('userId', (int) $authUser->id)
            ->first();

        if (!$batch) {
            return response()->json(ApiResponse::error('Batch no encontrado', null, 404), 404);
        }

        $errorItems = BatchItem::query()
            ->where('batchId', $batch->id)
            ->where('status', 'error')
            ->orderBy('id')
            ->get();

        // Las columnas de rawData cambian por tipo de carga: se usa la unión de todas las filas.
        $rawDataColumns = [];

        foreach ($errorItems as $item) {
            foreach (array_keys($this->toArrayPayload($item->rawData)) as $column) {
                $rawDataColumns[(string) $column] = true;
            }
        }

        $rawDataColumns = array_keys($rawDataColumns);
        $headers = array_merge(['batchItemId'], $rawDataColumns, ['errorType', 'errorMessage']);

        $rows = [];

        foreach ($errorItems as $item) {
            $rawData = $this->toArrayPayload($item->rawData);
            $errorLog = $this->toArrayPayload($item->errorLog);

            $row = [(string) $item->id];

            foreach ($rawDataColumns as $column) {
                $row[] = $this->stringifyCell($rawData[$column] ?? null);
            }

            $row[] = $this->stringifyCell($errorLog['type'] ?? null);
            $row[] = $errorLog === []
                ? $this->stringifyCell($item->errorLog)
                : $this->stringifyCell($errorLog['message'] ?? $errorLog);

            $rows[] = $row;
        }

        $filename = sprintf('batch_%d_errores_%s.csv', $batch->id, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM para que Excel abra el CSV en UTF-8.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function toArrayPayload($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function stringifyCell($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
