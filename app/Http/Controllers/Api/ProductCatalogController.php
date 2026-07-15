<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCatalogResource;
use App\Models\ProductCatalog;
use App\Models\ProductClassification;
use App\Services\ProductClassificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ProductCatalogController extends Controller
{
    public function __construct(
        private readonly ProductClassificationService $productClassificationService
    ) {
    }

    public function getAll(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 100));
        $page = max(1, (int) $request->query('page', 1));

        $products = ProductCatalog::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        // idProducto en productcatalog puede traer espacios sueltos de la fuente
        // (mismo idProducto visible con/sin espacio = mismo producto para efectos
        // de clasificación), así que el match se hace sobre el valor trimeado.
        $trimmedIds = $products->getCollection()
            ->map(fn ($product) => trim($product->idProducto))
            ->unique()
            ->values()
            ->all();

        $classifications = ProductClassification::query()
            ->whereIn('idProducto', $trimmedIds)
            ->pluck('clasificacion', 'idProducto');

        $products->getCollection()->each(function ($product) use ($classifications) {
            $product->clasificacion = $classifications[trim($product->idProducto)] ?? null;
        });

        $products->setCollection(ProductCatalogResource::collection($products->getCollection())->collection);

        return response()->json(ApiResponse::success('Productos', $products));
    }

    public function classify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idProducto' => ['required', 'string', 'max:50'],
            'clasificacion' => ['required', 'string', 'in:' . ProductClassification::RODAMIENTOS . ',' . ProductClassification::NO_RODAMIENTOS],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        try {
            $classification = $this->productClassificationService->classify(
                (string) $request->input('idProducto'),
                (string) $request->input('clasificacion')
            );
        } catch (RuntimeException $e) {
            return response()->json(
                ApiResponse::error('Datos inválidos', ['idProducto' => [$e->getMessage()]], 422),
                422
            );
        }

        return response()->json(ApiResponse::success('Clasificación guardada', [
            'id' => $classification->id,
            'idProducto' => $classification->idProducto,
            'clasificacion' => $classification->clasificacion,
        ]));
    }
}
