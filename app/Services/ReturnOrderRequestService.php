<?php

namespace App\Services;

use App\Models\ReturnOrder;
use App\Models\ReturnOrderRequest;
use App\Models\ReturnOrderRequestItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnOrderRequestService
{
    /**
     * Vincula una orden de devolución a una request existente de tipo material return.
     * Auto-crea un ReturnOrderRequestItem por cada producto de la orden.
     */
    public function linkToRequest(int $returnOrderId, int $requestId, float $returnChargePercent): ReturnOrderRequest
    {
        $returnOrder = ReturnOrder::with('items')->findOrFail($returnOrderId);

        if ($returnOrder->items->isEmpty()) {
            throw new RuntimeException('La orden de devolución no tiene productos.');
        }

        $alreadyLinked = ReturnOrderRequest::where('returnOrderId', $returnOrderId)->exists();
        if ($alreadyLinked) {
            throw new RuntimeException('Esta orden de devolución ya está vinculada a una solicitud.');
        }

        return DB::transaction(function () use ($returnOrder, $requestId, $returnChargePercent) {
            $returnOrderRequest = ReturnOrderRequest::create([
                'returnOrderId'       => $returnOrder->id,
                'requestId'           => $requestId,
                'returnChargePercent' => $returnChargePercent,
            ]);

            foreach ($returnOrder->items as $item) {
                ReturnOrderRequestItem::create([
                    'returnOrderRequestId' => $returnOrderRequest->id,
                    'returnOrderItemId'    => $item->id,
                    'partNumber'           => self::extractPartNumber($item->descripcion),
                    'sapId'                => null,
                ]);
            }

            $returnOrder->update(['orderStatus' => 1]);

            return $returnOrderRequest->load(['returnOrder', 'items.returnOrderItem']);
        });
    }

    /**
     * Retorna el vínculo con todos sus items (incluyendo datos del returnOrderItem)
     * enriquecidos con los campos de solo lectura para la vista del revisor.
     */
    public function getById(int $returnOrderRequestId): ReturnOrderRequest
    {
        return ReturnOrderRequest::with([
            'returnOrder',
            'items.returnOrderItem',
        ])->findOrFail($returnOrderRequestId);
    }

    /**
     * Retorna el vínculo por requestId.
     */
    public function getByRequestId(int $requestId): ReturnOrderRequest
    {
        $record = ReturnOrderRequest::with([
            'returnOrder.chargeType',
            'items.returnOrderItem',
        ])->where('requestId', $requestId)->first();

        if (!$record) {
            throw new RuntimeException('No se encontró una orden de devolución vinculada a esta solicitud.');
        }

        return $record;
    }

    /**
     * Actualiza los campos del revisor para un item específico.
     * Valida que warehouseAccepted no supere replenishmentAccepted.
     */
    public function updateItem(int $returnOrderRequestId, int $itemId, array $data): ReturnOrderRequestItem
    {
        $item = ReturnOrderRequestItem::where('returnOrderRequestId', $returnOrderRequestId)
            ->where('id', $itemId)
            ->firstOrFail();

        $replenishmentAccepted = $data['replenishmentAccepted'] ?? $item->replenishmentAccepted;
        $warehouseAccepted     = $data['warehouseAccepted'] ?? $item->warehouseAccepted;

        if ($warehouseAccepted !== null && $replenishmentAccepted !== null) {
            if ($warehouseAccepted > $replenishmentAccepted) {
                throw new RuntimeException(
                    "Warehouse accepted ({$warehouseAccepted}) no puede ser mayor a replenishment accepted ({$replenishmentAccepted})."
                );
            }
        }

        $item->update(array_filter([
            'sapId'                          => $data['sapId'] ?? $item->sapId,
            'replenishmentAccepted'           => $data['replenishmentAccepted'] ?? null,
            'replenishmentReasonForRejection' => $data['replenishmentReasonForRejection'] ?? null,
            'warehouseReceived'               => $data['warehouseReceived'] ?? null,
            'warehouseAccepted'               => $data['warehouseAccepted'] ?? null,
            'warehouseReasonForRejection'     => $data['warehouseReasonForRejection'] ?? null,
        ], fn ($v) => $v !== null));

        return $item->fresh()->load('returnOrderItem');
    }

    /**
     * Actualiza múltiples items del revisor en una sola transacción.
     */
    public function updateItems(int $returnOrderRequestId, array $items): ReturnOrderRequest
    {
        DB::transaction(function () use ($returnOrderRequestId, $items) {
            foreach ($items as $data) {
                $this->updateItem($returnOrderRequestId, (int) $data['id'], $data);
            }
        });

        return $this->getById($returnOrderRequestId);
    }

    /**
     * Extrae el part number de la descripcion del XML.
     * Formato esperado: ^PARTNUMBER;^...
     */
    public static function extractPartNumber(string $descripcion): ?string
    {
        if (preg_match('/^\^([^;]+);/', $descripcion, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
