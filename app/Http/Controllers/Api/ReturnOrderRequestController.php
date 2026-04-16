<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReturnOrders\LinkReturnOrderToRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnOrders\LinkReturnOrderToRequestInput;
use App\Http\Requests\ReturnOrders\UpdateReturnOrderRequestItemInput;
use App\Http\Resources\ReturnOrderRequestItemResource;
use App\Http\Resources\ReturnOrderRequestResource;
use App\Services\ReturnOrderRequestService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class ReturnOrderRequestController extends Controller
{
    public function __construct(
        private readonly ReturnOrderRequestService $returnOrderRequestService,
        private readonly LinkReturnOrderToRequestAction $linkReturnOrderToRequestAction,
    ) {
    }

    /**
     * Vincula una orden de devolución a una request de tipo material return.
     * Auto-crea los items del revisor por cada producto de la orden.
     * POST /return-order-requests
     */
    public function store(LinkReturnOrderToRequestInput $request)
    {
        try {
            $returnOrderRequest = $this->linkReturnOrderToRequestAction->execute(
                $request->integer('returnOrderId'),
                $request->integer('requestId'),
                (float) ($request->input('returnChargePercent', 0)),
            );

            return response()->json(
                ApiResponse::success('Orden vinculada a la solicitud', new ReturnOrderRequestResource($returnOrderRequest), 201),
                201
            );
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }
    }

    /**
     * Retorna el vínculo con todos sus items (vista del revisor).
     * GET /return-order-requests/{id}
     */
    public function show(int $id)
    {
        try {
            $returnOrderRequest = $this->returnOrderRequestService->getById($id);

            return response()->json(ApiResponse::success('Material return', new ReturnOrderRequestResource($returnOrderRequest)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('No encontrado', null, 404), 404);
        }
    }

    /**
     * Retorna el vínculo por requestId.
     * GET /return-order-requests/by-request/{requestId}
     */
    public function showByRequestId(int $requestId)
    {
        try {
            $returnOrderRequest = $this->returnOrderRequestService->getByRequestId($requestId);

            return response()->json(ApiResponse::success('Material return', new ReturnOrderRequestResource($returnOrderRequest)));
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 404), 404);
        }
    }

    /**
     * El revisor actualiza los campos de un producto.
     * PUT /return-order-requests/{id}/items/{itemId}
     */
    public function updateItem(UpdateReturnOrderRequestItemInput $request, int $id, int $itemId)
    {
        try {
            $item = $this->returnOrderRequestService->updateItem($id, $itemId, $request->validated());

            return response()->json(ApiResponse::success('Producto actualizado', new ReturnOrderRequestItemResource($item)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('Producto no encontrado', null, 404), 404);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }
    }
}
