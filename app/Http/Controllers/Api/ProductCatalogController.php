<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCatalogResource;
use App\Models\ProductCatalog;
use App\Models\ProductClassification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductCatalogController extends Controller
{
    public function getAll(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 100));
        $page = max(1, (int) $request->query('page', 1));

        $products = ProductCatalog::query()
            ->with('classification')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $products->setCollection(ProductCatalogResource::collection($products->getCollection())->collection);

        return response()->json(ApiResponse::success('Productos', $products));
    }

    public function classify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idProducto' => ['required', 'string', 'max:50', 'exists:productcatalog,idProducto'],
            'clasificacion' => ['required', 'string', 'in:' . ProductClassification::RODAMIENTOS . ',' . ProductClassification::NO_RODAMIENTOS],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $classification = ProductClassification::updateOrCreate(
            ['idProducto' => $request->input('idProducto')],
            ['clasificacion' => $request->input('clasificacion')]
        );

        return response()->json(ApiResponse::success('Clasificación guardada', [
            'id' => $classification->id,
            'idProducto' => $classification->idProducto,
            'clasificacion' => $classification->clasificacion,
        ]));
    }
}
