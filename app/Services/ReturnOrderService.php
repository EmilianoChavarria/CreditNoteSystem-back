<?php

namespace App\Services;

use App\Models\ReturnOrder;
use App\Models\ReturnOrderItem;
use App\Models\ReturnOrderItemHistory;
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
        $hasClientTable = Schema::hasTable($clientTable);

        $query = ReturnOrder::
        with('items')->
        orderByDesc('createdAt');

        if ($hasClientTable) {
            $columns = Schema::getColumnListing($clientTable);
            $hasRazonSocial = in_array('razonSocial', $columns, true);
            $hasIdCliente   = in_array('idCliente', $columns, true);

            $query->leftJoin(
                $clientTable . ' as cl',
                'cl.idCliente',
                '=',
                'returnorders.clientId'
            );

            $query->where(function ($q) use ($search, $hasRazonSocial, $hasIdCliente) {
                if ($hasRazonSocial) {
                    $q->orWhere('cl.razonSocial', 'LIKE', "%{$search}%");
                }

                if ($hasIdCliente && is_numeric($search)) {
                    $q->orWhere('returnorders.clientId', (int) $search);
                }
            });

            $query->select(
                'returnorders.*',
                $hasRazonSocial ? 'cl.razonSocial' : DB::raw('NULL as razonSocial')
            )->where('orderStatus', '0');
            ;
        } else {
            if (is_numeric($search)) {
                $query->where('clientId', (int) $search);
            }

            $query->select('returnorders.*', DB::raw('NULL as razonSocial'));
        }

        $orders = $query->get();

        return $orders->map(function (ReturnOrder $order) {
            return array_merge($order->toArray(), [
                'razonSocial' => $order->razonSocial ?? null,
            ]);
        })->values()->all();
    }

    /**
     * Retorna todas las órdenes de devolución de un cliente con sus items.
     */
    public function getReturnOrdersByClientId(int $clientId): Collection
    {
        return ReturnOrder::with('items')
            ->where('clientId', $clientId)
            ->orderByDesc('createdAt')
            ->get();
    }

    /**
     * Retorna el detalle de una orden de devolución.
     */
    public function getReturnOrderById(int $returnOrderId): ReturnOrder
    {
        return ReturnOrder::with('items')->findOrFail($returnOrderId);
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
                'status'       => 'in process',
                'notes'        => $notes,
                'chargeTypeId' => $chargeTypeId,
                'customRate'   => $customRate,
                'currency'     => $currency,
            ]);

            foreach ($items as $itemData) {
                $concepto = $this->getConceptoByIndex(
                    $itemData['invoiceFolio'],
                    $itemData['invoiceClientId'],
                    $itemData['conceptoIndex']
                );

                $returnOrderItem = ReturnOrderItem::create([
                    'returnOrderId'     => $returnOrder->id,
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
            }

            return $returnOrder->load('items');
        });
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
}
