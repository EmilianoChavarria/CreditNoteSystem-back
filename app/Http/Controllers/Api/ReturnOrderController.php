<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReturnOrders\CreateReturnOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnOrders\AddReturnOrderItemsInput;
use App\Http\Requests\ReturnOrders\CreateReturnOrderInput;
use App\Http\Requests\ReturnOrders\UpdateReturnOrderChargeInput;
use App\Http\Requests\ReturnOrders\UpdateReturnOrderItemQuantityInput;
use App\Http\Resources\ReturnOrderResource;
use App\Services\ReturnOrderService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use RuntimeException;

class ReturnOrderController extends Controller
{
    public function __construct(
        private readonly ReturnOrderService $returnOrderService,
        private readonly CreateReturnOrderAction $createReturnOrderAction,
    ) {
    }

    /**
     * Productos de una factura con cantidad disponible a devolver.
     * GET /invoices/{folio}/products/{clientId}
     */
    public function getInvoiceProducts(string $folio, int $clientId)
    {
        try {
            $products = $this->returnOrderService->getInvoiceProducts($folio, $clientId);

            return response()->json(ApiResponse::success('Productos de la factura', $products));
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 404), 404);
        }
    }

    /**
     * Busca órdenes de devolución por clientId o razonSocial del cliente.
     * GET /return-orders/search?q=LAMINADORA
     * GET /return-orders/search?q=121693
     */
    public function search(Request $request)
    {
        $search = trim($request->query('q', ''));

        if ($search === '') {
            return response()->json(ApiResponse::error('El parámetro q es requerido.', null, 422), 422);
        }

        $orders = $this->returnOrderService->searchOrders($search);

        return response()->json(ApiResponse::success('Órdenes de devolución', $orders));
    }

    /**
     * Historial de devoluciones de un producto específico de una factura.
     * GET /invoices/{folio}/products/{clientId}/{conceptoIndex}/history
     */
    public function getProductReturnHistory(string $folio, int $clientId, int $conceptoIndex)
    {
        try {
            $data = $this->returnOrderService->getProductReturnHistory($folio, $clientId, $conceptoIndex);

            return response()->json(ApiResponse::success('Historial de devoluciones', $data));
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 404), 404);
        }
    }

    /**
     * Órdenes de devolución de un cliente.
     * GET /return-orders/client/{clientId}
     */
    public function getByClientId(int $clientId)
    {
        $orders = $this->returnOrderService->getReturnOrdersByClientId($clientId);

        return response()->json(ApiResponse::success('Órdenes de devolución', ReturnOrderResource::collection($orders)));
    }

    /**
     * Detalle de una orden de devolución.
     * GET /return-orders/{id}
     */
    public function show(int $id)
    {
        try {
            $order = $this->returnOrderService->getReturnOrderById($id);

            return response()->json(ApiResponse::success('Orden de devolución', new ReturnOrderResource($order)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('Orden no encontrada', null, 404), 404);
        }
    }

    /**
     * Crea una nueva orden de devolución.
     * POST /return-orders
     *
     * Body:
     * {
     *   "clientId": 121693,
     *   "notes": "...",
     *   "items": [
     *     { "invoiceFolio": "536662", "invoiceClientId": 121693, "conceptoIndex": 0, "requestedQuantity": 3 }
     *   ]
     * }
     */
    public function store(CreateReturnOrderInput $request)
    {
        try {
            $authUser = $request->user();
            $userId   = $authUser?->id;

            $order = $this->createReturnOrderAction->execute(
                $request->integer('clientId'),
                $userId,
                $request->input('items'),
                $request->input('notes'),
                $request->integer('chargeTypeId'),
                $request->input('customRate') !== null ? (float) $request->input('customRate') : null,
                $request->input('currency'),
            );

            return response()->json(
                ApiResponse::success('Orden de devolución creada', new ReturnOrderResource($order), 201),
                201
            );
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }
    }

    /**
     * Actualiza la configuración de cargo de una orden de devolución.
     * PATCH /return-orders/{id}/charge
     *
     * Body (todos opcionales/nullable):
     * { "chargeTypeId": 1 }            → porcentaje default del tipo
     * { "customRate": 12.5 }           → porcentaje personalizado
     * {}                               → sin cargo
     */
    public function updateCharge(UpdateReturnOrderChargeInput $request, int $id)
    {
        try {
            $chargeTypeId = $request->input('chargeTypeId') !== null ? $request->integer('chargeTypeId') : null;
            $customRate   = $request->input('customRate') !== null ? (float) $request->input('customRate') : null;

            $order = $this->returnOrderService->updateCharge($id, $chargeTypeId, $customRate);

            return response()->json(ApiResponse::success('Cargo actualizado', new ReturnOrderResource($order)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('Orden no encontrada', null, 404), 404);
        }
    }

    /**
     * Agrega materiales (de la misma factura o de otra) a una orden existente.
     * POST /return-orders/{id}/items
     */
    public function addItems(AddReturnOrderItemsInput $request, int $id)
    {
        try {
            $order = $this->returnOrderService->addItems($id, $request->input('items'));

            return response()->json(ApiResponse::success('Productos agregados', new ReturnOrderResource($order)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('Orden no encontrada', null, 404), 404);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }
    }

    /**
     * Modifica la cantidad solicitada de un material de la orden.
     * PATCH /return-orders/{id}/items/{itemId}
     */
    public function updateItemQuantity(UpdateReturnOrderItemQuantityInput $request, int $id, int $itemId)
    {
        try {
            $order = $this->returnOrderService->updateItemQuantity($id, $itemId, (float) $request->input('requestedQuantity'));

            return response()->json(ApiResponse::success('Cantidad actualizada', new ReturnOrderResource($order)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('Orden o producto no encontrado', null, 404), 404);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }
    }

    /**
     * Elimina (soft-delete) un material de la orden y libera su cantidad reservada.
     * DELETE /return-orders/{id}/items/{itemId}
     */
    public function removeItem(Request $request, int $id, int $itemId)
    {
        try {
            $order = $this->returnOrderService->removeItem($id, $itemId, $request->user()?->id);

            return response()->json(ApiResponse::success('Producto eliminado', new ReturnOrderResource($order)));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error('Orden o producto no encontrado', null, 404), 404);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 422), 422);
        }
    }
}
