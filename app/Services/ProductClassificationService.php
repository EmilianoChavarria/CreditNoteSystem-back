<?php

namespace App\Services;

use App\Models\ProductCatalog;
use App\Models\ProductClassification;
use RuntimeException;

class ProductClassificationService
{
    /**
     * Clasifica un producto por idProducto (trimeado — ver nota en
     * ProductCatalogController sobre variantes con/sin espacio de la fuente).
     * Usado tanto por el endpoint individual como por la carga masiva.
     *
     * El match ignora comillas dobles sueltas (`022085P-1-PULLEY"`): aparecen
     * tanto en los CSV que capturan los usuarios como en el propio catálogo, y
     * son el mismo producto. Se limpian de ambos lados, pero la clasificación
     * se guarda con el idProducto tal cual está en el catálogo, porque ese es
     * el valor con el que la leen ProductCatalogController y ForecastService.
     */
    public function classify(string $idProducto, string $clasificacion): ProductClassification
    {
        $idProducto = trim($idProducto);
        $cleanedId = $this->cleanIdProducto($idProducto);

        if (!in_array($clasificacion, [ProductClassification::RODAMIENTOS, ProductClassification::NO_RODAMIENTOS], true)) {
            throw new RuntimeException("Clasificación inválida: '{$clasificacion}'. Debe ser '" . ProductClassification::RODAMIENTOS . "' o '" . ProductClassification::NO_RODAMIENTOS . "'.");
        }

        if ($cleanedId === '') {
            throw new RuntimeException("El producto '{$idProducto}' no existe en el catálogo.");
        }

        // Puede haber más de una variante en el catálogo (con y sin comilla):
        // se clasifican todas para que el producto quede resuelto se vea como se vea.
        $catalogIds = ProductCatalog::query()
            // Se quita la comilla y luego se trimea (no al revés): una comilla
            // pegada a un espacio del borde dejaría el espacio suelto y el
            // valor ya no empataría con el lado de PHP.
            ->whereRaw('TRIM(REPLACE(idProducto, ?, ?)) = ?', ['"', '', $cleanedId])
            ->pluck('idProducto')
            ->map(fn ($catalogId) => trim((string) $catalogId))
            ->unique()
            ->values();

        if ($catalogIds->isEmpty()) {
            throw new RuntimeException("El producto '{$idProducto}' no existe en el catálogo.");
        }

        $classifications = $catalogIds->map(fn (string $catalogId) => ProductClassification::updateOrCreate(
            ['idProducto' => $catalogId],
            ['clasificacion' => $clasificacion]
        ));

        // Si el valor recibido coincide exacto con una variante, se devuelve esa.
        return $classifications->firstWhere('idProducto', $idProducto) ?? $classifications->first();
    }

    /** Quita únicamente comillas dobles; el resto del identificador se respeta. */
    private function cleanIdProducto(string $idProducto): string
    {
        return trim(str_replace('"', '', $idProducto));
    }
}
