<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Workflows ─────────────────────────────────────────────────────────────────
#[OA\Get(path: '/workflows', operationId: 'workflowsList', tags: ['Workflows'], summary: 'Listar todos los workflows',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de workflows', content: new OA\JsonContent(
        properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Workflow'))]
    ))]
)]
#[OA\Get(path: '/workflows/{id}', operationId: 'workflowsShow', tags: ['Workflows'], summary: 'Obtener workflow por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del workflow', content: new OA\JsonContent(ref: '#/components/schemas/Workflow')),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/workflows', operationId: 'workflowsStore', tags: ['Workflows'], summary: 'Crear workflow',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'requestTypeId'],
        properties: [
            new OA\Property(property: 'name',          type: 'string',  example: 'Flujo Nota de Credito'),
            new OA\Property(property: 'requestTypeId', type: 'integer', example: 1),
            new OA\Property(property: 'isActive',      type: 'boolean', nullable: true, example: true),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Workflow creado'),
        new OA\Response(response: 422, description: 'Ya existe un workflow activo para ese tipo y clasificacion'),
    ]
)]
#[OA\Put(path: '/workflows/{id}', operationId: 'workflowsUpdate', tags: ['Workflows'], summary: 'Actualizar workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'name',     type: 'string'),
            new OA\Property(property: 'isActive', type: 'boolean', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Workflow actualizado')]
)]
#[OA\Delete(path: '/workflows/{id}', operationId: 'workflowsDestroy', tags: ['Workflows'], summary: 'Eliminar workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Workflow eliminado'),
        new OA\Response(response: 422, description: 'No se puede eliminar: tiene solicitudes activas'),
    ]
)]
// ── Workflow Steps ────────────────────────────────────────────────────────────
#[OA\Get(path: '/workflowsteps', operationId: 'workflowStepsList', tags: ['WorkflowSteps'], summary: 'Listar todos los pasos de workflow',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de pasos')]
)]
#[OA\Get(path: '/workflowsteps/workflows', operationId: 'workflowStepsAllWorkflows', tags: ['WorkflowSteps'], summary: 'Todos los workflows activos con sus pasos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Workflows con sus pasos anidados')]
)]
#[OA\Get(path: '/workflowsteps/workflow/{workflowId}', operationId: 'workflowStepsByWorkflow', tags: ['WorkflowSteps'], summary: 'Pasos de un workflow especifico',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'workflowId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Pasos del workflow en orden'),
        new OA\Response(response: 404, description: 'Workflow no encontrado'),
    ]
)]
#[OA\Get(path: '/workflowsteps/{id}', operationId: 'workflowStepsShow', tags: ['WorkflowSteps'], summary: 'Obtener paso por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del paso'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/workflowsteps', operationId: 'workflowStepsStore', tags: ['WorkflowSteps'], summary: 'Crear paso de workflow con transiciones condicionales opcionales',
    description: 'Las transitions permiten enrutar al siguiente paso segun el valor de un campo de la solicitud. Si no se definen, el flujo es lineal por stepOrder. Operadores soportados: >, <, >=, <=, ==, !=.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['workflowId', 'stepName', 'stepOrder', 'roleId'],
        properties: [
            new OA\Property(property: 'workflowId',    type: 'integer', example: 1),
            new OA\Property(property: 'stepName',      type: 'string',  example: 'Aprobacion Gerente'),
            new OA\Property(property: 'stepOrder',     type: 'integer', example: 2),
            new OA\Property(property: 'roleId',        type: 'integer', example: 3),
            new OA\Property(property: 'isInitialStep', type: 'boolean', nullable: true, example: false),
            new OA\Property(property: 'isFinalStep',   type: 'boolean', nullable: true, example: false),
            new OA\Property(property: 'transitions',   type: 'array',   nullable: true,
                description: 'Transiciones condicionales. Si omitida, el siguiente paso se resuelve por stepOrder.',
                items: new OA\Items(
                    required: ['toStepId'],
                    properties: [
                        new OA\Property(property: 'toStepId',          type: 'integer', example: 5,               description: 'ID del paso destino'),
                        new OA\Property(property: 'conditionField',    type: 'string',  nullable: true, example: 'totalAmount', description: 'Campo de la solicitud a evaluar'),
                        new OA\Property(property: 'conditionOperator', type: 'string',  nullable: true, example: '>',           description: 'Operador: >, <, >=, <=, ==, !='),
                        new OA\Property(property: 'conditionValue',    type: 'string',  nullable: true, example: '150000',      description: 'Valor umbral (string)'),
                        new OA\Property(property: 'priority',          type: 'integer', nullable: true, example: 1,             description: 'Prioridad de evaluacion (menor = primero)'),
                    ]
                )
            ),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Paso creado con sus transiciones'),
        new OA\Response(response: 422, description: 'Error: paso inicial o final duplicado en el workflow'),
    ]
)]
#[OA\Put(path: '/workflowsteps/{id}', operationId: 'workflowStepsUpdate', tags: ['WorkflowSteps'], summary: 'Actualizar paso de workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'stepName',      type: 'string'),
            new OA\Property(property: 'stepOrder',     type: 'integer'),
            new OA\Property(property: 'roleId',        type: 'integer'),
            new OA\Property(property: 'isInitialStep', type: 'boolean', nullable: true),
            new OA\Property(property: 'isFinalStep',   type: 'boolean', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Paso actualizado')]
)]
#[OA\Delete(path: '/workflowsteps/{id}', operationId: 'workflowStepsDestroy', tags: ['WorkflowSteps'], summary: 'Eliminar paso de workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Paso eliminado'),
        new OA\Response(response: 422, description: 'No se puede eliminar: solicitudes activas en este paso'),
    ]
)]
// ── Batches ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/batches', operationId: 'batchesList', tags: ['Batches'], summary: 'Listar todos los lotes de carga masiva',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de lotes')]
)]
#[OA\Post(path: '/batches', operationId: 'batchesStore', tags: ['Batches'], summary: 'Crear lote de carga masiva (multipart/form-data)',
    description: "Sube uno o mas archivos y los procesa de forma asincrona via queue worker.\n\n**Tipos y requerimientos:**\n- `sapScreen`: PDFs/imagenes de SAP. Multiples archivos.\n- `creditsData`: Excel con columnas requestNumber, creditNumber.\n- `orderNumbers`: Excel con columnas requestNumber, orderNumber.\n- `newRequest`: Excel para creacion masiva de solicitudes. Requiere `requestTypeId`. Acepta nombres en lugar de IDs para customerId, reasonId, classificationId.\n- `uploadSupport`: Adjuntos en rango de solicitudes. Requiere `requestTypeId`, `minRange`, `maxRange`. Hasta 10 archivos.\n- `users`: Excel para creacion masiva de usuarios. Acepta roleName y supervisorId por nombre.",
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['batchType', 'file'],
            properties: [
                new OA\Property(property: 'batchType',             type: 'string',  enum: ['sapScreen', 'creditsData', 'orderNumbers', 'newRequest', 'uploadSupport', 'users'], example: 'newRequest'),
                new OA\Property(property: 'file',                  type: 'string',  format: 'binary',  description: 'Archivo(s) a procesar. Para sapScreen y uploadSupport se pueden enviar multiples archivos como file[].'),
                new OA\Property(property: 'requestTypeId',         type: 'integer', nullable: true,    description: 'Requerido para newRequest y uploadSupport'),
                new OA\Property(property: 'minRange',              type: 'integer', nullable: true,    description: 'Requerido para uploadSupport. Numero correlativo minimo de solicitud'),
                new OA\Property(property: 'maxRange',              type: 'integer', nullable: true,    description: 'Requerido para uploadSupport. Numero correlativo maximo de solicitud'),
                new OA\Property(property: 'welcomeEmailMode',      type: 'string',  nullable: true,    enum: ['none', 'individual', 'single'], description: 'Para batchType=users. Modo de envio de email de bienvenida'),
                new OA\Property(property: 'welcomeEmailRecipient', type: 'string',  nullable: true,    format: 'email', description: 'Para welcomeEmailMode=single: email destino unico'),
            ]
        )
    )),
    responses: [
        new OA\Response(response: 201, description: 'Lote creado y encolado para procesamiento asíncrono'),
        new OA\Response(response: 422, description: 'Datos invalidos, tipo no soportado o archivo con extension no permitida'),
    ]
)]
#[OA\Get(path: '/batches/{id}', operationId: 'batchesShow', tags: ['Batches'], summary: 'Estado y errores de un lote',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del lote con items y errores'),
        new OA\Response(response: 404, description: 'Lote no encontrado'),
    ]
)]
#[OA\Get(path: '/batches/{id}/requests', operationId: 'batchesRequests', tags: ['Batches'], summary: 'Solicitudes creadas por un lote',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Solicitudes generadas por el lote')]
)]
class WorkflowDocs
{
}
