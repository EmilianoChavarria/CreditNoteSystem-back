<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Services\Batches\BatchInputContext;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SapScreenBatchHandler extends AbstractBatchHandler
{
    public function batchType(): string
    {
        return 'sapScreen';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $rows = [];

        foreach ($context->storedFiles as $file) {
            $nameWithoutExtension = pathinfo((string) $file['originalName'], PATHINFO_FILENAME);

            $rows[] = [
                'requestNumber' => $nameWithoutExtension,
                'file' => $file,
            ];
        }

        return $rows;
    }

    public function process(array $row, Batch $batch): ?int
    {
        $requestNumber = trim((string) ($row['requestNumber'] ?? ''));
        if (!$requestNumber) {
            throw new RuntimeException('Nombre de archivo inválido: no se pudo extraer requestNumber.');
        }

        $request = RequestModel::where('requestNumber', $requestNumber)->first();

        if (!$request && is_numeric($requestNumber)) {
            $request = RequestModel::where('requestNumber', (int) $requestNumber)->first();
        }

        if (!$request) {
            throw new RuntimeException('Request no encontrada para requestNumber=' . $requestNumber);
        }

        $file = (array) ($row['file'] ?? []);
        $this->createAttachment($request, $file);

        $storedPath = (string) ($file['storedPath'] ?? '');
        $disk = (string) ($file['disk'] ?? 'local');

        try {
            $fileUrl = Storage::url($storedPath);
        } catch (\Exception $e) {
            $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
            $fileUrl = $appUrl . '/storage/' . ltrim($storedPath, '/');
        }

        $request->update(['sapReturnOrder' => $fileUrl]);

        return (int) $request->id;
    }
}
