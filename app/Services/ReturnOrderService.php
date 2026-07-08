<?php

namespace App\Services;

use App\Models\ReturnOrder;
use App\Models\ReturnOrderItem;
use App\Models\ReturnOrderItemHistory;
use App\Models\ReturnOrderRequest;
use App\Models\ReturnOrderRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReturnOrderService
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $invoiceConceptsCache = [];

    public function __construct(
        private readonly XmlInvoiceService $xmlInvoiceService,
        private readonly FesaWsService $fesaWsService,
    ) {
    }

    /**
     * Retorna los productos de una factura con sus cantidades disponibles a devolver.
     * Obtiene el XML directamente desde FESA usando el folio como idTransaccion.
     */
    public function getInvoiceProducts(string $invoiceFolio, int $clientId): array
    {
        return $this->getConceptosFromFesa($invoiceFolio, $clientId);
    }

    /**
     * Busca órdenes de devolución por clientId exacto o por razonSocial (LIKE).
     * Si la tabla de clientes existe se hace JOIN para incluir razonSocial en el resultado.
     * Si el término es numérico se busca también por clientId exacto.
     */
    public function searchOrders(string $search): array
    {
        $clientTable = 'clientes_TME700618RC7';
        $hasClientTable = Schema::connection('invoices')->hasTable($clientTable);

        $matchedClientIds = [];
        $razonSocialMap = collect();

        if ($hasClientTable) {
            $columns = Schema::connection('invoices')->getColumnListing($clientTable);
            $hasRazonSocial = in_array('razonSocial', $columns, true);
            $hasIdCliente   = in_array('idCliente', $columns, true);

            $clientQuery = DB::connection('invoices')->table($clientTable);

            $clientQuery->where(function ($q) use ($search, $hasRazonSocial, $hasIdCliente, &$hasAnyCondition) {
                $hasAnyCondition = false;

                if ($hasRazonSocial) {
                    $q->orWhere('razonSocial', 'LIKE', "%{$search}%");
                    $hasAnyCondition = true;
                }

                if ($hasIdCliente && is_numeric($search)) {
                    $q->orWhere('idCliente', $search);
                    $hasAnyCondition = true;
                }

                if (!$hasAnyCondition) {
                    $q->whereRaw('1 = 0');
                }
            });

            $clients = $clientQuery->get(['idCliente', 'razonSocial']);
            $matchedClientIds = $clients->pluck('idCliente')->all();
            $razonSocialMap   = $clients->pluck('razonSocial', 'idCliente');
        }

        $query = ReturnOrder::with('items')
            ->orderByDesc('createdAt');

        $query->where(function ($q) use ($search, $matchedClientIds) {
            if (!empty($matchedClientIds)) {
                $q->orWhereIn('clientId', $matchedClientIds);
            }

            if (is_numeric($search)) {
                $q->orWhere('clientId', (int) $search);
            }
        });

        if (empty($matchedClientIds) && !is_numeric($search)) {
            return [];
        }

        $orders = $query->get();

        return $orders->map(function (ReturnOrder $order) use ($razonSocialMap) {
            return array_merge($order->toArray(), [
                'razonSocial' => $razonSocialMap[(string) $order->clientId] ?? null,
            ]);
        })->values()->all();
    }

    /**
     * Retorna todas las órdenes de devolución de un cliente con sus items.
     */
    public function getReturnOrdersByClientId(int $clientId): Collection
    {
        return ReturnOrder::with(['items.requestItem', 'returnOrderRequest.request'])
            ->where('clientId', $clientId)
            ->orderByDesc('createdAt')
            ->get();
    }

    /**
     * Retorna el detalle de una orden de devolución.
     */
    public function getReturnOrderById(int $returnOrderId): ReturnOrder
    {
        return ReturnOrder::with(['items.requestItem', 'returnOrderRequest.request'])->findOrFail($returnOrderId);
    }

    /**
     * Crea una orden de devolución validando las cantidades disponibles por producto.
     *
     * $items debe ser un array de:
     * [
     *   'invoiceFolio'    => '536662',
     *   'invoiceClientId' => 121693,
     *   'conceptoIndex'   => 0,
     *   'requestedQuantity' => 3,
     * ]
     */
    public function createReturnOrder(int $clientId, ?int $userId, array $items, ?string $notes, int $chargeTypeId, ?float $customRate, string $currency): ReturnOrder
    {
        $this->validateItems($items);

        return DB::transaction(function () use ($clientId, $userId, $items, $notes, $chargeTypeId, $customRate, $currency) {
            $returnOrder = ReturnOrder::create([
                'clientId'     => $clientId,
                'userId'       => $userId,
                'status'       => 'pending',
                'notes'        => $notes,
                'chargeTypeId' => $chargeTypeId,
                'customRate'   => $customRate,
                'currency'     => $currency,
            ]);

            foreach ($items as $itemData) {
                $this->addOrMergeItem($returnOrder, $itemData, null);
            }

            return $returnOrder->load(['items.requestItem', 'returnOrderRequest.request']);
        });
    }

    /**
     * Agrega materiales (de la misma factura o de otra) a una orden ya existente.
     * Si la orden ya está vinculada a una request, también crea el ReturnOrderRequestItem
     * correspondiente para que el nuevo material entre al flujo de revisión.
     *
     * @throws RuntimeException si la orden no existe, la solicitud vinculada ya está finalizada,
     *                          o la cantidad solicitada excede la disponible.
     */
    public function addItems(int $returnOrderId, array $items): ReturnOrder
    {
        $returnOrder = ReturnOrder::findOrFail($returnOrderId);

        $this->assertOrderEditable($returnOrder);
        $this->validateItems($items);

        return DB::transaction(function () use ($returnOrder, $items) {
            $returnOrderRequest = $returnOrder->orderStatus
                ? ReturnOrderRequest::where('returnOrderId', $returnOrder->id)->first()
                : null;

            foreach ($items as $itemData) {
                $this->addOrMergeItem($returnOrder, $itemData, $returnOrderRequest);
            }

            return $returnOrder->fresh(['items.requestItem', 'returnOrderRequest.request']);
        });
    }

    /**
     * Modifica la cantidad solicitada de un material ya agregado a la orden.
     * Ajusta también el ReturnOrderItemHistory para que la diferencia (si se redujo)
     * quede disponible de nuevo para futuras órdenes.
     *
     * @throws RuntimeException si el producto ya tiene información de replenishment/almacén
     *                          capturada, la solicitud vinculada está finalizada, o la nueva
     *                          cantidad excede la disponible.
     */
    public function updateItemQuantity(int $returnOrderId, int $itemId, float $newQuantity): ReturnOrder
    {
        $returnOrder = ReturnOrder::findOrFail($returnOrderId);
        $item        = ReturnOrderItem::where('returnOrderId', $returnOrderId)->findOrFail($itemId);

        $this->assertOrderEditable($returnOrder);
        $this->assertItemNotReviewed($item, 'modificar la cantidad de');

        if ($newQuantity <= 0) {
            throw new RuntimeException('La cantidad a devolver debe ser mayor a 0.');
        }

        $concepto = $this->getConceptoByIndex($item->invoiceFolio, $item->invoiceClientId, $item->conceptoIndex);

        if ($concepto === null) {
            throw new RuntimeException("Concepto índice {$item->conceptoIndex} no encontrado en la factura {$item->invoiceFolio}.");
        }

        $returnedByOthers = $this->getTotalReturnedForProduct($item->invoiceFolio, $item->invoiceClientId, $item->conceptoIndex)
            - $item->requestedQuantity;
        $available = $concepto['cantidad'] - $returnedByOthers;

        if ($newQuantity > $available) {
            throw new RuntimeException("Cantidad solicitada {$newQuantity} excede la disponible {$available}.");
        }

        DB::transaction(function () use ($item, $newQuantity) {
            $item->update(['requestedQuantity' => $newQuantity]);

            ReturnOrderItemHistory::where('returnOrderItemId', $item->id)
                ->update(['returnedQuantity' => $newQuantity]);
        });

        return $returnOrder->fresh(['items.requestItem', 'returnOrderRequest.request']);
    }

    /**
     * Elimina (soft-delete) un material de la orden y libera su cantidad reservada
     * en el historial para que vuelva a estar disponible en futuras órdenes.
     *
     * @throws RuntimeException si el producto ya tiene información de replenishment/almacén
     *                          capturada, o la solicitud vinculada está finalizada.
     */
    public function removeItem(int $returnOrderId, int $itemId, ?int $userId): ReturnOrder
    {
        $returnOrder = ReturnOrder::findOrFail($returnOrderId);
        $item        = ReturnOrderItem::where('returnOrderId', $returnOrderId)->findOrFail($itemId);

        $this->assertOrderEditable($returnOrder);
        $this->assertItemNotReviewed($item, 'eliminar');

        DB::transaction(function () use ($item, $userId) {
            ReturnOrderItemHistory::where('returnOrderItemId', $item->id)
                ->update(['returnedQuantity' => 0]);

            ReturnOrderRequestItem::where('returnOrderItemId', $item->id)->delete();

            $item->update(['deletedBy' => $userId]);
            $item->delete();
        });

        return $returnOrder->fresh(['items.requestItem', 'returnOrderRequest.request']);
    }

    /**
     * Actualiza la configuración de cargo de una orden de devolución.
     *
     * Tres modos:
     *  - chargeTypeId  → porcentaje default del tipo; customRate se limpia
     *  - customRate    → porcentaje personalizado; chargeTypeId se limpia
     *  - ninguno       → sin cargo; ambos campos se limpian
     */
    public function updateCharge(int $returnOrderId, ?int $chargeTypeId, ?float $customRate): ReturnOrder
    {
        $returnOrder = ReturnOrder::findOrFail($returnOrderId);

        if ($chargeTypeId !== null) {
            $returnOrder->update(['chargeTypeId' => $chargeTypeId, 'customRate' => null]);
        } elseif ($customRate !== null) {
            $returnOrder->update(['chargeTypeId' => null, 'customRate' => $customRate]);
        } else {
            $returnOrder->update(['chargeTypeId' => null, 'customRate' => null]);
        }

        return $returnOrder->fresh();
    }

    /**
     * Retorna el historial de devoluciones de un producto específico de una factura.
     * Incluye resumen (totalSent, totalReturned, available) y el listado de órdenes.
     */
    public function getProductReturnHistory(string $invoiceFolio, int $clientId, int $conceptoIndex): array
    {
        $concepto = $this->getConceptoByIndex($invoiceFolio, $clientId, $conceptoIndex);

        if ($concepto === null) {
            throw new RuntimeException("Concepto índice {$conceptoIndex} no encontrado en la factura {$invoiceFolio}.");
        }

        $totalReturned = $this->getTotalReturnedForProduct($invoiceFolio, $clientId, $conceptoIndex);
        $available     = max(0, $concepto['cantidad'] - $totalReturned);

        $history = ReturnOrderItemHistory::where('returnorderitemhistory.invoiceFolio', $invoiceFolio)
            ->where('returnorderitemhistory.invoiceClientId', $clientId)
            ->where('returnorderitemhistory.conceptoIndex', $conceptoIndex)
            ->join('returnorderitems', 'returnorderitems.id', '=', 'returnorderitemhistory.returnOrderItemId')
            ->join('returnorders', 'returnorders.id', '=', 'returnorderitems.returnOrderId')
            ->select(
                'returnorderitemhistory.createdAt as date',
                'returnorders.id as returnOrderId',
                'returnorderitemhistory.returnedQuantity as quantity',
                'returnorders.status',
                'returnorders.notes',
            )
            ->orderByDesc('returnorderitemhistory.createdAt')
            ->get()
            ->map(fn ($row) => [
                'date'          => $row->date,
                'orderNumber'   => 'RO-' . str_pad($row->returnOrderId, 6, '0', STR_PAD_LEFT),
                'returnOrderId' => $row->returnOrderId,
                'quantity'      => (float) $row->quantity,
                'status'        => $row->status,
                'notes'         => $row->notes,
            ])
            ->values()
            ->all();

        return [
            'product' => [
                'invoiceFolio'  => $invoiceFolio,
                'conceptoIndex' => $conceptoIndex,
                'claveProdServ' => $concepto['claveProdServ'],
                'descripcion'   => $concepto['descripcion'],
                'claveUnidad'   => $concepto['claveUnidad'],
                'unidad'        => $concepto['unidad'],
                'valorUnitario' => $concepto['valorUnitario'],
            ],
            'summary' => [
                'totalSent'     => $concepto['cantidad'],
                'totalReturned' => $totalReturned,
                'available'     => $available,
                'unidad'        => $concepto['unidad'],
            ],
            'history' => $history,
        ];
    }

    /**
     * Valida que las cantidades solicitadas no excedan las disponibles.
     * Agrupa por factura+concepto para soportar múltiples líneas del mismo producto en una sola orden.
     *
     * @throws RuntimeException con detalle de cada producto inválido.
     */
    private function validateItems(array $items): void
    {
        // Agrupar por folio+clientId+conceptoIndex para sumar la cantidad pedida en la misma orden
        $requestedByKey = [];
        foreach ($items as $item) {
            $key = "{$item['invoiceFolio']}|{$item['invoiceClientId']}|{$item['conceptoIndex']}";
            $requestedByKey[$key] = ($requestedByKey[$key] ?? 0) + $item['requestedQuantity'];
        }

        $errors = [];

        foreach ($requestedByKey as $key => $requested) {
            [$folio, $clientId, $index] = explode('|', $key);
            $clientId = (int) $clientId;
            $index    = (int) $index;

            $concepto = $this->getConceptoByIndex($folio, $clientId, $index);

            if ($concepto === null) {
                $errors[] = "Concepto índice {$index} no encontrado en la factura {$folio}.";
                continue;
            }

            $alreadyReturned = $this->getTotalReturnedForProduct($folio, $clientId, $index);
            $available       = $concepto['cantidad'] - $alreadyReturned;

            if ($requested <= 0) {
                $errors[] = "Factura {$folio} concepto {$index}: la cantidad a devolver debe ser mayor a 0.";
            } elseif ($requested > $available) {
                $errors[] = "Factura {$folio} concepto {$index}: solicitado {$requested}, disponible {$available}.";
            }
        }

        if (!empty($errors)) {
            throw new RuntimeException(implode(' | ', $errors));
        }
    }

    /**
     * Suma el total devuelto históricamente para un producto de una factura específica.
     */
    private function getTotalReturnedForProduct(string $invoiceFolio, int $clientId, int $conceptoIndex): float
    {
        return (float) ReturnOrderItemHistory::where('invoiceFolio', $invoiceFolio)
            ->where('invoiceClientId', $clientId)
            ->where('conceptoIndex', $conceptoIndex)
            ->sum('returnedQuantity');
    }

    /**
     * Retorna un array indexado por conceptoIndex con el total devuelto,
     * útil para pasar al XmlInvoiceService y calcular availableQuantity.
     */
    private function getReturnedQuantitiesByIndex(string $invoiceFolio, int $clientId): array
    {
        return ReturnOrderItemHistory::where('invoiceFolio', $invoiceFolio)
            ->where('invoiceClientId', $clientId)
            ->selectRaw('conceptoIndex, SUM(returnedQuantity) as total')
            ->groupBy('conceptoIndex')
            ->pluck('total', 'conceptoIndex')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Obtiene conceptos desde FESA. Antes se leia storage/app/public/{folio}-{clientId}.xml,
     * pero el flujo de facturas ya consulta FESA por folio.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getConceptosFromFesa(string $invoiceFolio, int $clientId): array
    {
        $cacheKey = "{$invoiceFolio}|{$clientId}";

        if (!array_key_exists($cacheKey, $this->invoiceConceptsCache)) {
            $returnedByIndex = $this->getReturnedQuantitiesByIndex($invoiceFolio, $clientId);
            $xmlContent = $this->fesaWsService->fetchXmlString($invoiceFolio);
            $this->invoiceConceptsCache[$cacheKey] = $this->xmlInvoiceService->getConceptosFromXmlString($xmlContent, $returnedByIndex);
        }

        return $this->invoiceConceptsCache[$cacheKey];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getConceptoByIndex(string $invoiceFolio, int $clientId, int $conceptoIndex): ?array
    {
        $conceptos = $this->getConceptosFromFesa($invoiceFolio, $clientId);

        return $conceptos[$conceptoIndex] ?? null;
    }

    /**
     * Si la orden ya tiene una línea activa para el mismo folio+clientId+conceptoIndex,
     * suma la cantidad a esa línea existente en vez de crear una duplicada.
     * Si no existe, crea una línea nueva (y su ReturnOrderRequestItem si la orden está vinculada).
     *
     * @throws RuntimeException si la línea existente ya tiene información de replenishment/almacén capturada.
     */
    private function addOrMergeItem(ReturnOrder $returnOrder, array $itemData, ?ReturnOrderRequest $returnOrderRequest): ReturnOrderItem
    {
        $existing = ReturnOrderItem::where('returnOrderId', $returnOrder->id)
            ->where('invoiceFolio', $itemData['invoiceFolio'])
            ->where('invoiceClientId', $itemData['invoiceClientId'])
            ->where('conceptoIndex', $itemData['conceptoIndex'])
            ->first();

        if ($existing !== null) {
            $this->assertItemNotReviewed($existing, 'modificar la cantidad de');

            $newQuantity = $existing->requestedQuantity + $itemData['requestedQuantity'];

            $existing->update(['requestedQuantity' => $newQuantity]);

            ReturnOrderItemHistory::where('returnOrderItemId', $existing->id)
                ->update(['returnedQuantity' => $newQuantity]);

            return $existing;
        }

        $returnOrderItem = $this->createItemWithHistory($returnOrder->id, $itemData);

        if ($returnOrderRequest !== null) {
            ReturnOrderRequestItem::create([
                'returnOrderRequestId' => $returnOrderRequest->id,
                'returnOrderItemId'    => $returnOrderItem->id,
                'partNumber'           => ReturnOrderRequestService::extractPartNumber($returnOrderItem->descripcion),
                'sapId'                => null,
            ]);
        }

        return $returnOrderItem;
    }

    /**
     * Crea un ReturnOrderItem (snapshot del producto de la factura) y su
     * ReturnOrderItemHistory correspondiente, que "reserva" la cantidad solicitada.
     */
    private function createItemWithHistory(int $returnOrderId, array $itemData): ReturnOrderItem
    {
        $concepto = $this->getConceptoByIndex(
            $itemData['invoiceFolio'],
            $itemData['invoiceClientId'],
            $itemData['conceptoIndex']
        );

        $returnOrderItem = ReturnOrderItem::create([
            'returnOrderId'     => $returnOrderId,
            'invoiceFolio'      => $itemData['invoiceFolio'],
            'invoiceClientId'   => $itemData['invoiceClientId'],
            'conceptoIndex'     => $itemData['conceptoIndex'],
            'claveProdServ'     => $concepto['claveProdServ'],
            'descripcion'       => ltrim(strtok($concepto['descripcion'], ';'), '^') . ';',
            'claveUnidad'       => $concepto['claveUnidad'],
            'unidad'            => $concepto['unidad'],
            'valorUnitario'     => $concepto['valorUnitario'],
            'originalQuantity'  => $concepto['cantidad'],
            'requestedQuantity' => $itemData['requestedQuantity'],
        ]);

        ReturnOrderItemHistory::create([
            'invoiceFolio'      => $itemData['invoiceFolio'],
            'invoiceClientId'   => $itemData['invoiceClientId'],
            'conceptoIndex'     => $itemData['conceptoIndex'],
            'returnOrderItemId' => $returnOrderItem->id,
            'returnedQuantity'  => $itemData['requestedQuantity'],
        ]);

        return $returnOrderItem;
    }

    /**
     * Bloquea la edición de una orden cuando ya está vinculada a una request
     * y esa request quedó finalizada (released, rejected o cancelled).
     */
    private function assertOrderEditable(ReturnOrder $returnOrder): void
    {
        if (!$returnOrder->orderStatus) {
            return;
        }

        $returnOrderRequest = ReturnOrderRequest::with('request')
            ->where('returnOrderId', $returnOrder->id)
            ->first();

        if ($returnOrderRequest?->request !== null && $returnOrderRequest->request->isFinalized()) {
            throw new RuntimeException('No se puede editar: la solicitud vinculada ya está finalizada.');
        }
    }

    /**
     * Bloquea modificar/eliminar un item cuando el revisor (replenishment/almacén)
     * ya capturó alguna cantidad para él.
     */
    private function assertItemNotReviewed(ReturnOrderItem $item, string $action): void
    {
        $requestItem = ReturnOrderRequestItem::where('returnOrderItemId', $item->id)->first();

        if ($requestItem !== null && $requestItem->hasReviewData()) {
            throw new RuntimeException("No se puede {$action} este producto: ya tiene información de replenishment/almacén capturada.");
        }
    }
}
