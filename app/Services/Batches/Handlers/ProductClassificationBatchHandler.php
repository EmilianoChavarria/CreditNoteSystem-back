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

    public function buildRows(BatchInputContext $context): iterable
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
            'idProducto' => $this->cleanIdProducto($this->value($row, ['idproducto', 'id_producto', 'producto', 'sku', 'clave'])),
            'clasificacion' => $this->value($row, ['clasificacion', 'clasificaci_n', 'clasificación', 'tipo', 'categoria']),
        ], [
            'idProducto' => ['required', 'string', 'max:191'],
            'clasificacion' => ['required', 'string'],
        ]);

        $clasificacion = $this->normalizeClasificacion((string) $data['clasificacion']);

        $this->productClassificationService->classify((string) $data['idProducto'], $clasificacion);

        return null;
    }

    /**
     * Los CSV traen comillas dobles sueltas pegadas al id (`022085P-1-PULLEY"`).
     * Se quitan aquí para que no rompan el `max` ni el mensaje de error; el
     * match contra el catálogo también las ignora (ProductClassificationService).
     */
    private function cleanIdProducto(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return trim(str_replace('"', '', $value));
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
