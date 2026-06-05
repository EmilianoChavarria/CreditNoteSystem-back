<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Schemas reutilizables (modelos). Se referencian en los endpoints como:
 *   ref: '#/components/schemas/NombreDelSchema'
 */
class SchemasDocs
{
    // ── Auth ──────────────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'AuthToken', type: 'object', properties: [
        new OA\Property(property: 'token',     type: 'string',  example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
        new OA\Property(property: 'expiresAt', type: 'string',  format: 'date-time'),
    ])]
    public static function authTokenSchema(): void {}

    // ── Users & Roles ─────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'Role', type: 'object', properties: [
        new OA\Property(property: 'id',               type: 'integer', example: 2),
        new OA\Property(property: 'roleName',         type: 'string',  example: 'REPLENISHMENT'),
        new OA\Property(property: 'color',            type: 'string',  nullable: true, example: '#3B82F6'),
        new OA\Property(property: 'equivalentRoleId', type: 'integer', nullable: true, example: 5,
            description: 'Rol fallback cuando no hay usuario disponible con este rol en el siguiente paso'),
    ])]
    public static function roleSchema(): void {}

    #[OA\Schema(schema: 'User', type: 'object', properties: [
        new OA\Property(property: 'id',                type: 'integer', example: 12),
        new OA\Property(property: 'fullName',          type: 'string',  example: 'Juan Perez'),
        new OA\Property(property: 'email',             type: 'string',  format: 'email', example: 'juan@empresa.com'),
        new OA\Property(property: 'roleId',            type: 'integer', example: 2),
        new OA\Property(property: 'role',              ref: '#/components/schemas/Role', nullable: true),
        new OA\Property(property: 'supervisorId',      type: 'integer', nullable: true),
        new OA\Property(property: 'clientId',          type: 'string',  nullable: true),
        new OA\Property(property: 'preferredLanguage', type: 'string',  enum: ['en', 'es'], example: 'es'),
        new OA\Property(property: 'isActive',          type: 'boolean', example: true),
        new OA\Property(property: 'createdAt',         type: 'string',  format: 'date-time'),
        new OA\Property(property: 'updatedAt',         type: 'string',  format: 'date-time'),
    ])]
    public static function userSchema(): void {}

    // ── Request Types & Classifications ───────────────────────────────────────
    #[OA\Schema(schema: 'RequestType', type: 'object', properties: [
        new OA\Property(property: 'id',     type: 'integer', example: 1),
        new OA\Property(property: 'name',   type: 'string',  example: 'Nota de Credito'),
        new OA\Property(property: 'prefix', type: 'string',  nullable: true, example: 'NC'),
    ])]
    public static function requestTypeSchema(): void {}

    #[OA\Schema(schema: 'Classification', type: 'object', properties: [
        new OA\Property(property: 'id',            type: 'integer', example: 3),
        new OA\Property(property: 'name',          type: 'string',  example: 'Devolucion por defecto de fabrica'),
        new OA\Property(property: 'requestTypeId', type: 'integer', example: 1),
        new OA\Property(property: 'type',          type: 'string',  nullable: true, example: 'ventas',
            description: 'Clasifica el manager: ventas, finanzas o marketing'),
    ])]
    public static function classificationSchema(): void {}

    // ── Requests ──────────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'Request', type: 'object', description: 'Solicitud con su estado en el workflow', properties: [
        new OA\Property(property: 'id',               type: 'integer', example: 45),
        new OA\Property(property: 'requestNumber',    type: 'string',  example: 'NC-000045'),
        new OA\Property(property: 'status',           type: 'string',  enum: ['draft', 'pending', 'approved', 'rejected', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'requestTypeId',    type: 'integer', example: 1),
        new OA\Property(property: 'requestType',      ref: '#/components/schemas/RequestType', nullable: true),
        new OA\Property(property: 'classificationId', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'classification',   ref: '#/components/schemas/Classification', nullable: true),
        new OA\Property(property: 'userId',           type: 'integer', example: 12),
        new OA\Property(property: 'clientId',         type: 'string',  nullable: true, example: '121693'),
        new OA\Property(property: 'reasonId',         type: 'integer', nullable: true),
        new OA\Property(property: 'notes',            type: 'string',  nullable: true),
        new OA\Property(property: 'totalAmount',      type: 'number',  nullable: true, example: 50000.00),
        new OA\Property(property: 'currentStep',      ref: '#/components/schemas/WorkflowCurrentStep', nullable: true),
        new OA\Property(property: 'createdAt',        type: 'string',  format: 'date-time'),
        new OA\Property(property: 'updatedAt',        type: 'string',  format: 'date-time'),
    ])]
    public static function requestSchema(): void {}

    // ── Workflow ──────────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'WorkflowStepTransition', type: 'object',
        description: 'Transicion condicional. Evalua campo de la solicitud con operador y valor. La ruta sin condicion es el default.',
        properties: [
        new OA\Property(property: 'id',                type: 'integer', example: 1),
        new OA\Property(property: 'fromStepId',        type: 'integer', example: 2),
        new OA\Property(property: 'toStepId',          type: 'integer', example: 5),
        new OA\Property(property: 'conditionField',    type: 'string',  nullable: true, example: 'totalAmount'),
        new OA\Property(property: 'conditionOperator', type: 'string',  nullable: true, enum: ['>', '<', '>=', '<=', '==', '!='], example: '>'),
        new OA\Property(property: 'conditionValue',    type: 'string',  nullable: true, example: '150000'),
        new OA\Property(property: 'priority',          type: 'integer', example: 1, description: 'Menor numero = mayor prioridad de evaluacion'),
    ])]
    public static function workflowStepTransitionSchema(): void {}

    #[OA\Schema(schema: 'WorkflowStep', type: 'object', properties: [
        new OA\Property(property: 'id',            type: 'integer', example: 3),
        new OA\Property(property: 'workflowId',    type: 'integer', example: 1),
        new OA\Property(property: 'stepName',      type: 'string',  example: 'Aprobacion Gerente'),
        new OA\Property(property: 'stepOrder',     type: 'integer', example: 2),
        new OA\Property(property: 'roleId',        type: 'integer', example: 4),
        new OA\Property(property: 'role',          ref: '#/components/schemas/Role', nullable: true),
        new OA\Property(property: 'isInitialStep', type: 'boolean', example: false),
        new OA\Property(property: 'isFinalStep',   type: 'boolean', example: false),
        new OA\Property(property: 'transitions',   type: 'array', nullable: true,
            items: new OA\Items(ref: '#/components/schemas/WorkflowStepTransition')),
    ])]
    public static function workflowStepSchema(): void {}

    #[OA\Schema(schema: 'Workflow', type: 'object', properties: [
        new OA\Property(property: 'id',            type: 'integer', example: 1),
        new OA\Property(property: 'name',          type: 'string',  example: 'Flujo Nota de Credito'),
        new OA\Property(property: 'requestTypeId', type: 'integer', example: 1),
        new OA\Property(property: 'isActive',      type: 'boolean', example: true),
        new OA\Property(property: 'steps',         type: 'array', nullable: true,
            items: new OA\Items(ref: '#/components/schemas/WorkflowStep')),
        new OA\Property(property: 'createdAt',     type: 'string',  format: 'date-time'),
    ])]
    public static function workflowSchema(): void {}

    #[OA\Schema(schema: 'WorkflowCurrentStep', type: 'object',
        description: 'Paso actual de la solicitud. assignedUserId es null para roles broadcast (REPLENISHMENT, WAREHOUSE, IT).',
        properties: [
        new OA\Property(property: 'id',             type: 'integer', example: 78),
        new OA\Property(property: 'requestId',      type: 'integer', example: 45),
        new OA\Property(property: 'workflowStepId', type: 'integer', example: 3),
        new OA\Property(property: 'assignedRoleId', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'assignedUserId', type: 'integer', nullable: true, example: 12),
        new OA\Property(property: 'status',         type: 'string',  enum: ['pending', 'approved', 'rejected'], example: 'pending'),
        new OA\Property(property: 'assignedRole',   ref: '#/components/schemas/Role', nullable: true),
        new OA\Property(property: 'assignedUser',   ref: '#/components/schemas/User', nullable: true),
    ])]
    public static function workflowCurrentStepSchema(): void {}

    #[OA\Schema(schema: 'WorkflowHistory', type: 'object', description: 'Registro de cada accion del flujo de aprobacion', properties: [
        new OA\Property(property: 'id',             type: 'integer', example: 101),
        new OA\Property(property: 'requestId',      type: 'integer', example: 45),
        new OA\Property(property: 'workflowStepId', type: 'integer', example: 3),
        new OA\Property(property: 'actionUserId',   type: 'integer', example: 12),
        new OA\Property(property: 'actionUser',     ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'actionType',     type: 'string',  enum: ['approved', 'rejected', 'cancelled', 'sent_back'], example: 'approved'),
        new OA\Property(property: 'comments',       type: 'string',  nullable: true),
        new OA\Property(property: 'createdAt',      type: 'string',  format: 'date-time'),
    ])]
    public static function workflowHistorySchema(): void {}

    // ── Batches ───────────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'BatchItem', type: 'object', properties: [
        new OA\Property(property: 'id',        type: 'integer', example: 1),
        new OA\Property(property: 'batchId',   type: 'integer', example: 5),
        new OA\Property(property: 'rowNumber', type: 'integer', example: 2),
        new OA\Property(property: 'status',    type: 'string',  enum: ['pending', 'processing', 'done', 'failed'], example: 'done'),
        new OA\Property(property: 'errorLog',  type: 'string',  nullable: true, example: 'Cliente no encontrado: ACME CORP'),
        new OA\Property(property: 'rawData',   type: 'object',  nullable: true, description: 'Fila original del archivo como objeto JSON'),
    ])]
    public static function batchItemSchema(): void {}

    #[OA\Schema(schema: 'Batch', type: 'object', properties: [
        new OA\Property(property: 'id',             type: 'integer', example: 5),
        new OA\Property(property: 'batchType',      type: 'string',  enum: ['sapScreen', 'creditsData', 'orderNumbers', 'newRequest', 'uploadSupport', 'users'], example: 'newRequest'),
        new OA\Property(property: 'userId',         type: 'integer', example: 12),
        new OA\Property(property: 'status',         type: 'string',  enum: ['pending', 'processing', 'done', 'failed'], example: 'done'),
        new OA\Property(property: 'totalItems',     type: 'integer', example: 50),
        new OA\Property(property: 'processedItems', type: 'integer', example: 48),
        new OA\Property(property: 'failedItems',    type: 'integer', example: 2),
        new OA\Property(property: 'items',          type: 'array', nullable: true,
            items: new OA\Items(ref: '#/components/schemas/BatchItem')),
        new OA\Property(property: 'createdAt',      type: 'string',  format: 'date-time'),
    ])]
    public static function batchSchema(): void {}

    // ── Return Orders ─────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'ReturnOrderItem', type: 'object', description: 'Producto incluido en una orden de devolucion', properties: [
        new OA\Property(property: 'id',                type: 'integer', example: 1),
        new OA\Property(property: 'returnOrderId',     type: 'integer', example: 10),
        new OA\Property(property: 'invoiceFolio',      type: 'string',  example: '536662'),
        new OA\Property(property: 'invoiceClientId',   type: 'integer', example: 121693),
        new OA\Property(property: 'conceptoIndex',     type: 'integer', example: 0),
        new OA\Property(property: 'claveProdServ',     type: 'string',  example: '31161500'),
        new OA\Property(property: 'descripcion',       type: 'string',  example: 'TORNILLO HEXAGONAL M8x30;'),
        new OA\Property(property: 'claveUnidad',       type: 'string',  example: 'H87'),
        new OA\Property(property: 'unidad',            type: 'string',  example: 'Pieza'),
        new OA\Property(property: 'valorUnitario',     type: 'number',  example: 12.50),
        new OA\Property(property: 'originalQuantity',  type: 'number',  example: 100.0, description: 'Cantidad facturada originalmente'),
        new OA\Property(property: 'requestedQuantity', type: 'number',  example: 5.0,   description: 'Cantidad solicitada a devolver'),
    ])]
    public static function returnOrderItemSchema(): void {}

    #[OA\Schema(schema: 'ReturnOrder', type: 'object', description: 'Orden de devolucion de productos de una factura', properties: [
        new OA\Property(property: 'id',           type: 'integer', example: 10),
        new OA\Property(property: 'clientId',     type: 'integer', example: 121693),
        new OA\Property(property: 'userId',       type: 'integer', nullable: true, example: 12),
        new OA\Property(property: 'status',       type: 'string',  enum: ['in process', 'cancelled', 'released'], example: 'in process'),
        new OA\Property(property: 'currency',     type: 'string',  enum: ['MXN', 'USD'], example: 'MXN'),
        new OA\Property(property: 'chargeTypeId', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'customRate',   type: 'number',  nullable: true, example: 12.5,
            description: 'Porcentaje personalizado. Si null, se usa el porcentaje del chargeType'),
        new OA\Property(property: 'notes',        type: 'string',  nullable: true),
        new OA\Property(property: 'orderStatus',  type: 'boolean', example: false, description: 'false=activa, true=cerrada'),
        new OA\Property(property: 'items',        type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ReturnOrderItem')),
        new OA\Property(property: 'createdAt',    type: 'string',  format: 'date-time'),
        new OA\Property(property: 'updatedAt',    type: 'string',  format: 'date-time'),
    ])]
    public static function returnOrderSchema(): void {}

    #[OA\Schema(schema: 'ReturnOrderRequestItem', type: 'object',
        description: 'Item de devolucion con resultado de inspeccion (reposicion + almacen)', properties: [
        new OA\Property(property: 'id',                              type: 'integer', example: 1),
        new OA\Property(property: 'returnOrderRequestId',            type: 'integer', example: 3),
        new OA\Property(property: 'returnOrderItemId',               type: 'integer', example: 7),
        new OA\Property(property: 'sapId',                           type: 'string',  nullable: true, example: '100045'),
        new OA\Property(property: 'replenishmentAccepted',           type: 'number',  nullable: true, example: 4.0),
        new OA\Property(property: 'replenishmentReasonForRejection', type: 'string',  nullable: true),
        new OA\Property(property: 'warehouseReceived',               type: 'number',  nullable: true, example: 5.0),
        new OA\Property(property: 'warehouseAccepted',               type: 'number',  nullable: true, example: 4.0),
        new OA\Property(property: 'warehouseReasonForRejection',     type: 'string',  nullable: true),
    ])]
    public static function returnOrderRequestItemSchema(): void {}

    #[OA\Schema(schema: 'ReturnOrderRequest', type: 'object',
        description: 'Vinculo entre una ReturnOrder y una Request de tipo devolucion de material', properties: [
        new OA\Property(property: 'id',                  type: 'integer', example: 3),
        new OA\Property(property: 'returnOrderId',       type: 'integer', example: 10),
        new OA\Property(property: 'requestId',           type: 'integer', example: 45),
        new OA\Property(property: 'returnChargePercent', type: 'number',  nullable: true, minimum: 0, maximum: 100, example: 10.0),
        new OA\Property(property: 'returnOrder',         ref: '#/components/schemas/ReturnOrder', nullable: true),
        new OA\Property(property: 'items',               type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ReturnOrderRequestItem')),
        new OA\Property(property: 'createdAt',           type: 'string',  format: 'date-time'),
    ])]
    public static function returnOrderRequestSchema(): void {}

    // ── Charge Policies ───────────────────────────────────────────────────────
    #[OA\Schema(schema: 'ChargePolicy', type: 'object',
        description: 'Politica de cargo segun dias transcurridos desde la factura', properties: [
        new OA\Property(property: 'id',          type: 'integer', example: 1),
        new OA\Property(property: 'day',         type: 'integer', example: 30, description: 'Dias transcurridos desde la factura'),
        new OA\Property(property: 'conditional', type: 'string',  nullable: true),
        new OA\Property(property: 'percentage',  type: 'number',  example: 10.5, description: 'Porcentaje a cobrar (0-100)'),
        new OA\Property(property: 'deletedAt',   type: 'string',  nullable: true, format: 'date-time'),
    ])]
    public static function chargePolicySchema(): void {}

    // ── Notifications ─────────────────────────────────────────────────────────
    #[OA\Schema(schema: 'Notification', type: 'object', properties: [
        new OA\Property(property: 'id',        type: 'integer', example: 55),
        new OA\Property(property: 'userId',    type: 'integer', example: 12),
        new OA\Property(property: 'title',     type: 'string',  example: 'Solicitud NC-000045 requiere tu aprobacion'),
        new OA\Property(property: 'body',      type: 'string',  nullable: true),
        new OA\Property(property: 'type',      type: 'string',  example: 'request_pending'),
        new OA\Property(property: 'data',      type: 'object',  nullable: true, description: 'Payload adicional (requestId, etc.)'),
        new OA\Property(property: 'readAt',    type: 'string',  nullable: true, format: 'date-time'),
        new OA\Property(property: 'createdAt', type: 'string',  format: 'date-time'),
    ])]
    public static function notificationSchema(): void {}

    // ── Shared response wrappers ──────────────────────────────────────────────
    #[OA\Schema(schema: 'ApiResponse', type: 'object', description: 'Envelope estandar de todas las respuestas de la API', properties: [
        new OA\Property(property: 'ok',      type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string',  example: 'Operacion exitosa'),
        new OA\Property(property: 'data',    nullable: true,  description: 'Payload (objeto, array o null)'),
    ])]
    public static function apiResponseSchema(): void {}

    #[OA\Schema(schema: 'PaginatedResponse', type: 'object', description: 'Respuesta paginada estandar de Laravel', properties: [
        new OA\Property(property: 'data',          type: 'array',   items: new OA\Items()),
        new OA\Property(property: 'current_page',  type: 'integer', example: 1),
        new OA\Property(property: 'per_page',      type: 'integer', example: 15),
        new OA\Property(property: 'total',         type: 'integer', example: 150),
        new OA\Property(property: 'last_page',     type: 'integer', example: 10),
        new OA\Property(property: 'from',          type: 'integer', example: 1),
        new OA\Property(property: 'to',            type: 'integer', example: 15),
    ])]
    public static function paginatedResponseSchema(): void {}
}
