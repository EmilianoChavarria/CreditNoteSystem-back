<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\ProductClassificationService;
use RuntimeException;

class ProductClassificationBatchHandler extends AbstractBatchHandler
{
    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly ProductClassificationService $productClassificationService,
    ) {
    }

    public function batchType(): string
    {
        return 'productClassification';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para productClassification.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    public function process(array $row, Batch $batch): ?int
    {
        $data = $this->validateRow([
            'idProducto' => $this->value($row, ['idproducto', 'id_producto', 'producto', 'sku', 'clave']),
            'clasificacion' => $this->value($row, ['clasificacion', 'clasificaci_n', 'clasificación', 'tipo', 'categoria']),
        ], [
            'idProducto' => ['required', 'string', 'max:50'],
            'clasificacion' => ['required', 'string'],
        ]);

        $clasificacion = $this->normalizeClasificacion((string) $data['clasificacion']);

        $this->productClassificationService->classify((string) $data['idProducto'], $clasificacion);

        return null;
    }

    /**
     * Carga masiva = usuarios tecleando a mano en Excel, así que aquí se
     * tolera mayúsculas/minúsculas y espacios extra. El endpoint individual
     * de clasificación se queda estricto (match exacto).
     */
    private function normalizeClasificacion(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if (in_array($normalized, ['rodamientos', 'rodamiento'], true)) {
            return 'Rodamientos';
        }

        if (in_array($normalized, ['no rodamientos', 'no rodamiento', 'norodamientos'], true)) {
            return 'No Rodamientos';
        }

        throw new RuntimeException("Clasificación no reconocida: '{$value}'. Debe ser 'Rodamientos' o 'No Rodamientos'.");
    }
}
