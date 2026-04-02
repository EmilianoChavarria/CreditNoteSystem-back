<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Services\Batches\BatchInputContext;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SapReturnOrderBatchHandler extends AbstractBatchHandler
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

        // Buscar la solicitud por requestNumber (puede ser string o int)
        $request = RequestModel::where('requestNumber', $requestNumber)->first();

        // Si no encuentra y es numérico, intentar como integer
        if (!$request && is_numeric($requestNumber)) {
            $request = RequestModel::where('requestNumber', (int) $requestNumber)->first();
        }

        if (!$request) {
            throw new RuntimeException('Request no encontrada para requestNumber=' . $requestNumber);
        }

        // Crear el adjunto
        $file = (array) ($row['file'] ?? []);
        $this->createAttachment($request, $file);

        // Generar URL accesible para el archivo
        $storedPath = (string) ($file['storedPath'] ?? '');
        $disk = (string) ($file['disk'] ?? 'local');

        // Construir URL pública usando Storage::url()
        try {
            // Storage::url() genera la URL completa para el path almacenado
            $fileUrl = Storage::url((string) $storedPath);
        } catch (\Exception $e) {
            // Fallback: usar una URL de acceso directo
            $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
            $fileUrl = $appUrl . '/storage/' . ltrim($storedPath, '/');
        }

        // Actualizar campo sapReturnOrder con la URL
        $request->update([
            'sapReturnOrder' => $fileUrl,
        ]);

        return (int) $request->id;
    }
}
