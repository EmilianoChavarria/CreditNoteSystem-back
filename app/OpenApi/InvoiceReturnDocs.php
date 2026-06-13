<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Invoices ──────────────────────────────────────────────────────────────────
#[OA\Get(path: '/invoices', operationId: 'invoicesList', tags: ['Invoices'], summary: 'Listar todas las facturas (desde BD local)',
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
#[OA\Get(path: '/invoices/{clientId}/search', operationId: 'invoicesSearchByClient', tags: ['Invoices'], summary: 'Buscar facturas paginadas de un cliente',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'clientId',       in: 'path',  required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'uuid',           in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'folio',          in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'receptorRfc',    in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'receptorNombre', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'moneda',         in: 'query', schema: new OA\Schema(type: 'string', enum: ['MXN', 'USD'])),
        new OA\Parameter(name: 'fechaInicial',   in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'fechaFinal',     in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'per_page',       in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'perPage',        in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',           in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Facturas paginadas filtradas')]
)]
#[OA\Get(path: '/invoices/{clientId}/charge-type/{chargeType}', operationId: 'invoicesByClientAndChargeType', tags: ['Invoices'],
    summary: 'Facturas paginadas de un cliente filtradas por tipo de cargo',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'clientId',   in: 'path',  required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'chargeType', in: 'path',  required: true, schema: new OA\Schema(type: 'string', example: 'annual')),
        new OA\Parameter(name: 'per_page',   in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'perPage',    in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',       in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'search',     in: 'query', description: 'Busca por folio de factura', schema: new OA\Schema(type: 'string')),
    ],
    responses: [new OA\Response(response: 200, description: 'Facturas paginadas del cliente y tipo de cargo')]
)]
#[OA\Get(path: '/invoices/{folio}/products/{clientId}', operationId: 'invoicesProducts', tags: ['Invoices'],
    summary: 'Productos de una factura con cantidades disponibles a devolver',
    description: 'Obtiene el XML de la factura desde FESA y calcula cantidad disponible (facturada - ya devuelta) por concepto.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'folio',    in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '536662')),
        new OA\Parameter(name: 'clientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 121693)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Lista de conceptos con cantidad disponible'),
        new OA\Response(response: 404, description: 'Factura no encontrada en FESA'),
    ]
)]
#[OA\Get(path: '/invoices/{folio}/products/{clientId}/{conceptoIndex}/history', operationId: 'invoicesProductHistory', tags: ['Invoices'],
    summary: 'Historial de devoluciones de un concepto especifico de una factura',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'folio',         in: 'path', required: true, schema: new OA\Schema(type: 'string',  example: '536662')),
        new OA\Parameter(name: 'clientId',      in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 121693)),
        new OA\Parameter(name: 'conceptoIndex', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 0)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Historial con resumen (totalSent, totalReturned, available) y lista de ordenes'),
        new OA\Response(response: 404, description: 'Concepto no encontrado en la factura'),
    ]
)]
// ── Return Orders ─────────────────────────────────────────────────────────────
#[OA\Get(path: '/return-orders/search', operationId: 'returnOrdersSearch', tags: ['ReturnOrders'],
    summary: 'Buscar ordenes de devolucion por clientId o razon social',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'ACME'))],
    responses: [new OA\Response(response: 200, description: 'Ordenes encontradas')]
)]
#[OA\Get(path: '/return-orders/client/{clientId}', operationId: 'returnOrdersByClient', tags: ['ReturnOrders'],
    summary: 'Ordenes de devolucion de un cliente',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'clientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 121693))],
    responses: [new OA\Response(response: 200, description: 'Ordenes del cliente con sus items')]
)]
#[OA\Get(path: '/return-orders/{id}', operationId: 'returnOrdersShow', tags: ['ReturnOrders'],
    summary: 'Obtener orden de devolucion por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Orden de devolucion con items', content: new OA\JsonContent(ref: '#/components/schemas/ReturnOrder')),
        new OA\Response(response: 404, description: 'No encontrada'),
    ]
)]
#[OA\Post(path: '/return-orders', operationId: 'returnOrdersStore', tags: ['ReturnOrders'],
    summary: 'Crear orden de devolucion',
    description: 'Valida que las cantidades solicitadas no excedan las disponibles (facturadas - ya devueltas). Se ejecuta en transaccion: crea ReturnOrder, ReturnOrderItems e historial.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['clientId', 'currency', 'chargeTypeId', 'items'],
        properties: [
            new OA\Property(property: 'clientId',     type: 'integer', example: 121693),
            new OA\Property(property: 'currency',     type: 'string',  enum: ['MXN', 'USD'], example: 'MXN'),
            new OA\Property(property: 'chargeTypeId', type: 'integer', example: 1),
            new OA\Property(property: 'customRate',   type: 'number',  nullable: true, example: 12.5, description: 'Porcentaje personalizado; si se envía, sobreescribe el tipo de cargo'),
            new OA\Property(property: 'notes',        type: 'string',  nullable: true),
            new OA\Property(property: 'items',        type: 'array',   minItems: 1,
                items: new OA\Items(
                    required: ['invoiceFolio', 'invoiceClientId', 'conceptoIndex', 'requestedQuantity'],
                    properties: [
                        new OA\Property(property: 'invoiceFolio',      type: 'string',  example: '536662'),
                        new OA\Property(property: 'invoiceClientId',   type: 'integer', example: 121693),
                        new OA\Property(property: 'conceptoIndex',     type: 'integer', example: 0,   description: 'Indice 0-based del concepto en el XML de la factura'),
                        new OA\Property(property: 'requestedQuantity', type: 'number',  example: 3.0, description: 'Debe ser mayor a 0 y no exceder la cantidad disponible'),
                    ]
                )
            ),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Orden de devolucion creada', content: new OA\JsonContent(ref: '#/components/schemas/ReturnOrder')),
        new OA\Response(response: 422, description: 'Cantidades invalidas o excedidas, o factura no encontrada en FESA'),
    ]
)]
#[OA\Patch(path: '/return-orders/{id}/charge', operationId: 'returnOrdersUpdateCharge', tags: ['ReturnOrders'],
    summary: 'Actualizar configuracion de cargo de una orden de devolucion',
    description: "Tres modos:\n- Enviar `chargeTypeId`: usa el porcentaje default del tipo (limpia customRate)\n- Enviar `customRate`: porcentaje personalizado (limpia chargeTypeId)\n- Enviar body vacio `{}`: sin cargo (limpia ambos)",
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'chargeTypeId', type: 'integer', nullable: true, example: 2),
            new OA\Property(property: 'customRate',   type: 'number',  nullable: true, example: 15.0),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Cargo actualizado'),
        new OA\Response(response: 404, description: 'Orden no encontrada'),
    ]
)]
// ── Return Order Requests ─────────────────────────────────────────────────────
#[OA\Post(path: '/return-order-requests', operationId: 'returnOrderRequestsStore', tags: ['ReturnOrderRequests'],
    summary: 'Vincular orden de devolucion a una solicitud de material',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['returnOrderId', 'requestId'],
        properties: [
            new OA\Property(property: 'returnOrderId',       type: 'integer', example: 10),
            new OA\Property(property: 'requestId',           type: 'integer', example: 45),
            new OA\Property(property: 'returnChargePercent', type: 'number',  nullable: true, minimum: 0, maximum: 100, example: 10.0),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Vinculo creado'),
        new OA\Response(response: 422, description: 'Orden o solicitud no existe'),
    ]
)]
#[OA\Get(path: '/return-order-requests/by-request/{requestId}', operationId: 'returnOrderRequestsByRequest', tags: ['ReturnOrderRequests'],
    summary: 'Devoluciones vinculadas a una solicitud especifica',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Devoluciones de la solicitud con sus items')]
)]
#[OA\Get(path: '/return-order-requests/{id}', operationId: 'returnOrderRequestsShow', tags: ['ReturnOrderRequests'],
    summary: 'Obtener vinculo devolucion-solicitud por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Vinculo con items'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Put(path: '/return-order-requests/{id}/items', operationId: 'returnOrderRequestsUpdateItems', tags: ['ReturnOrderRequests'],
    summary: 'Actualizar multiples items de devolucion vinculada (bulk)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['items'],
        properties: [
            new OA\Property(property: 'items', type: 'array', minItems: 1,
                items: new OA\Items(
                    required: ['id'],
                    properties: [
                        new OA\Property(property: 'id',                              type: 'integer', example: 12),
                        new OA\Property(property: 'sapId',                           type: 'string',  nullable: true, example: '100045'),
                        new OA\Property(property: 'replenishmentAccepted',           type: 'number',  nullable: true, example: 2.0),
                        new OA\Property(property: 'replenishmentReasonForRejection', type: 'string',  nullable: true),
                        new OA\Property(property: 'warehouseReceived',               type: 'number',  nullable: true, example: 2.0),
                        new OA\Property(property: 'warehouseAccepted',               type: 'number',  nullable: true, example: 1.5),
                        new OA\Property(property: 'warehouseReasonForRejection',     type: 'string',  nullable: true),
                    ]
                )
            ),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Items actualizados'),
        new OA\Response(response: 422, description: 'Datos invalidos'),
    ]
)]
#[OA\Put(path: '/return-order-requests/{id}/items/{itemId}', operationId: 'returnOrderRequestsUpdateItem', tags: ['ReturnOrderRequests'],
    summary: 'Actualizar un item individual de devolucion vinculada',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id',     in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'sapId',                           type: 'string',  nullable: true, example: '100045'),
            new OA\Property(property: 'replenishmentAccepted',           type: 'number',  nullable: true, example: 2.0),
            new OA\Property(property: 'replenishmentReasonForRejection', type: 'string',  nullable: true),
            new OA\Property(property: 'warehouseReceived',               type: 'number',  nullable: true, example: 2.0),
            new OA\Property(property: 'warehouseAccepted',               type: 'number',  nullable: true, example: 1.5),
            new OA\Property(property: 'warehouseReasonForRejection',     type: 'string',  nullable: true),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Item actualizado'),
        new OA\Response(response: 404, description: 'Item no encontrado'),
    ]
)]
class InvoiceReturnDocs
{
}
