<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Workflows ─────────────────────────────────────────────────────────────────
#[OA\Get(path: '/workflows', operationId: 'workflowsList', tags: ['Workflows'], summary: 'Listar todos los workflows',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de workflows')]
)]
#[OA\Get(path: '/workflows/{id}', operationId: 'workflowsShow', tags: ['Workflows'], summary: 'Obtener workflow por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del workflow'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/workflows', operationId: 'workflowsStore', tags: ['Workflows'], summary: 'Crear workflow',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'requestTypeId'],
        properties: [
            new OA\Property(property: 'name',          type: 'string'),
            new OA\Property(property: 'requestTypeId', type: 'integer'),
            new OA\Property(property: 'isActive',      type: 'boolean', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Workflow creado')]
)]
#[OA\Put(path: '/workflows/{id}', operationId: 'workflowsUpdate', tags: ['Workflows'], summary: 'Actualizar workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'name', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Workflow actualizado')]
)]
#[OA\Delete(path: '/workflows/{id}', operationId: 'workflowsDestroy', tags: ['Workflows'], summary: 'Eliminar workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Workflow eliminado')]
)]
// ── Workflow Steps ────────────────────────────────────────────────────────────
#[OA\Get(path: '/workflowsteps', operationId: 'workflowStepsList', tags: ['WorkflowSteps'], summary: 'Listar todos los pasos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de pasos')]
)]
#[OA\Get(path: '/workflowsteps/workflows', operationId: 'workflowStepsAllWorkflows', tags: ['WorkflowSteps'], summary: 'Todos los workflows activos con sus pasos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Workflows con pasos')]
)]
#[OA\Get(path: '/workflowsteps/workflow/{workflowId}', operationId: 'workflowStepsByWorkflow', tags: ['WorkflowSteps'], summary: 'Pasos de un workflow',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'workflowId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Pasos del workflow'),
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
#[OA\Post(path: '/workflowsteps', operationId: 'workflowStepsStore', tags: ['WorkflowSteps'], summary: 'Crear paso de workflow',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['workflowId', 'stepName', 'stepOrder', 'roleId'],
        properties: [
            new OA\Property(property: 'workflowId',    type: 'integer'),
            new OA\Property(property: 'stepName',      type: 'string'),
            new OA\Property(property: 'stepOrder',     type: 'integer'),
            new OA\Property(property: 'roleId',        type: 'integer'),
            new OA\Property(property: 'isInitialStep', type: 'boolean', nullable: true),
            new OA\Property(property: 'isFinalStep',   type: 'boolean', nullable: true),
            new OA\Property(property: 'transitions',   type: 'array', nullable: true, items: new OA\Items(
                properties: [
                    new OA\Property(property: 'toStepId',          type: 'integer'),
                    new OA\Property(property: 'conditionField',    type: 'string',  nullable: true),
                    new OA\Property(property: 'conditionOperator', type: 'string',  nullable: true),
                    new OA\Property(property: 'conditionValue',    type: 'string',  nullable: true),
                    new OA\Property(property: 'priority',          type: 'integer', nullable: true),
                ]
            )),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Paso creado'),
        new OA\Response(response: 422, description: 'Error: paso inicial/final duplicado'),
    ]
)]
#[OA\Put(path: '/workflowsteps/{id}', operationId: 'workflowStepsUpdate', tags: ['WorkflowSteps'], summary: 'Actualizar paso',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'stepName', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Paso actualizado')]
)]
#[OA\Delete(path: '/workflowsteps/{id}', operationId: 'workflowStepsDestroy', tags: ['WorkflowSteps'], summary: 'Eliminar paso',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Paso eliminado'),
        new OA\Response(response: 422, description: 'No se puede eliminar'),
    ]
)]
// ── Batches ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/batches', operationId: 'batchesList', tags: ['Batches'], summary: 'Listar todos los lotes',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de lotes')]
)]
#[OA\Post(path: '/batches', operationId: 'batchesStore', tags: ['Batches'], summary: 'Crear lote de solicitudes',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds'],
        properties: [
            new OA\Property(property: 'requestIds', type: 'array', items: new OA\Items(type: 'integer')),
            new OA\Property(property: 'notes',      type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Lote creado')]
)]
#[OA\Get(path: '/batches/{id}', operationId: 'batchesShow', tags: ['Batches'], summary: 'Obtener lote por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del lote'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Get(path: '/batches/{id}/requests', operationId: 'batchesRequests', tags: ['Batches'], summary: 'Solicitudes de un lote',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Solicitudes del lote')]
)]
class WorkflowDocs
{
}
