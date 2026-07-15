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
     */
    public function classify(string $idProducto, string $clasificacion): ProductClassification
    {
        $idProducto = trim($idProducto);

        if (!in_array($clasificacion, [ProductClassification::RODAMIENTOS, ProductClassification::NO_RODAMIENTOS], true)) {
            throw new RuntimeException("Clasificación inválida: '{$clasificacion}'. Debe ser '" . ProductClassification::RODAMIENTOS . "' o '" . ProductClassification::NO_RODAMIENTOS . "'.");
        }

        $existsInCatalog = ProductCatalog::query()
            ->whereRaw('TRIM(idProducto) = ?', [$idProducto])
            ->exists();

        if (!$existsInCatalog) {
            throw new RuntimeException("El producto '{$idProducto}' no existe en el catálogo.");
        }

        return ProductClassification::updateOrCreate(
            ['idProducto' => $idProducto],
            ['clasificacion' => $clasificacion]
        );
    }
}
