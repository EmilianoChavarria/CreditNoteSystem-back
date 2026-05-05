<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Invoices ──────────────────────────────────────────────────────────────────
#[OA\Get(path: '/invoices', operationId: 'invoicesList', tags: ['Invoices'], summary: 'Listar todas las facturas',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de facturas')]
)]
#[OA\Get(path: '/invoices/{clientId}', operationId: 'invoicesByClient', tags: ['Invoices'], summary: 'Facturas de un cliente',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'clientId', in: 'path',  required: true,  schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'serie',    in: 'query', required: false, schema: new OA\Schema(type: 'string')),
    ],
    responses: [new OA\Response(response: 200, description: 'Facturas del cliente')]
)]
#[OA\Get(path: '/invoices/{folio}/products/{clientId}', operationId: 'invoicesProducts', tags: ['Invoices'],
    summary: 'Productos de factura con cantidades disponibles a devolver',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'folio',    in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'clientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Productos con cantidad disponible'),
        new OA\Response(response: 404, description: 'Factura no encontrada'),
    ]
)]
#[OA\Get(path: '/invoices/{folio}/products/{clientId}/{conceptoIndex}/history', operationId: 'invoicesProductHistory', tags: ['Invoices'],
    summary: 'Historial de devoluciones de un concepto de factura',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'folio',         in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'clientId',      in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'conceptoIndex', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Historial del producto')]
)]
// ── Return Orders ─────────────────────────────────────────────────────────────
#[OA\Get(path: '/return-orders/search', operationId: 'returnOrdersSearch', tags: ['ReturnOrders'], summary: 'Buscar ordenes de devolucion',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'search', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
    responses: [new OA\Response(response: 200, description: 'Ordenes encontradas')]
)]
#[OA\Get(path: '/return-orders/client/{clientId}', operationId: 'returnOrdersByClient', tags: ['ReturnOrders'], summary: 'Ordenes de devolucion de un cliente',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'clientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Ordenes del cliente')]
)]
#[OA\Get(path: '/return-orders/{id}', operationId: 'returnOrdersShow', tags: ['ReturnOrders'], summary: 'Obtener orden de devolucion por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Orden de devolucion'),
        new OA\Response(response: 404, description: 'No encontrada'),
    ]
)]
#[OA\Post(path: '/return-orders', operationId: 'returnOrdersStore', tags: ['ReturnOrders'], summary: 'Crear orden de devolucion',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['clientId', 'items'],
        properties: [
            new OA\Property(property: 'clientId', type: 'integer'),
            new OA\Property(property: 'notes',    type: 'string', nullable: true),
            new OA\Property(property: 'items',    type: 'array', items: new OA\Items(
                required: ['invoiceFolio', 'invoiceClientId', 'conceptoIndex', 'requestedQuantity'],
                properties: [
                    new OA\Property(property: 'invoiceFolio',      type: 'string'),
                    new OA\Property(property: 'invoiceClientId',   type: 'integer'),
                    new OA\Property(property: 'conceptoIndex',     type: 'integer'),
                    new OA\Property(property: 'requestedQuantity', type: 'number'),
                ]
            )),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Orden creada'),
        new OA\Response(response: 422, description: 'Cantidades invalidas o excedidas'),
    ]
)]
// ── Return Order Requests ─────────────────────────────────────────────────────
#[OA\Post(path: '/return-order-requests', operationId: 'returnOrderRequestsStore', tags: ['ReturnOrderRequests'],
    summary: 'Vincular orden de devolucion a solicitud',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['returnOrderId', 'requestId'],
        properties: [
            new OA\Property(property: 'returnOrderId', type: 'integer'),
            new OA\Property(property: 'requestId',     type: 'integer'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Vinculo creado')]
)]
#[OA\Get(path: '/return-order-requests/by-request/{requestId}', operationId: 'returnOrderRequestsByRequest', tags: ['ReturnOrderRequests'],
    summary: 'Devoluciones vinculadas a una solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Devoluciones de la solicitud')]
)]
#[OA\Get(path: '/return-order-requests/{id}', operationId: 'returnOrderRequestsShow', tags: ['ReturnOrderRequests'],
    summary: 'Obtener vinculo devolucion-solicitud por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Vinculo'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Put(path: '/return-order-requests/{id}/items/{itemId}', operationId: 'returnOrderRequestsUpdateItem', tags: ['ReturnOrderRequests'],
    summary: 'Actualizar item de devolucion vinculada',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id',     in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'approvedQuantity', type: 'number')]
    )),
    responses: [new OA\Response(response: 200, description: 'Item actualizado')]
)]
class InvoiceReturnDocs
{
}
