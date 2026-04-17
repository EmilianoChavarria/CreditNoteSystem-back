<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/charge-policies', operationId: 'chargePoliciesList', tags: ['ChargePolicies'], summary: 'Listar politicas de cargo',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de politicas ordenadas por dia')]
)]
#[OA\Post(path: '/charge-policies/sync', operationId: 'chargePoliciesSync', tags: ['ChargePolicies'],
    summary: 'Crear o actualizar politicas en lote (upsert)',
    description: 'Cada item sin id se crea; con id se actualiza. Operacion atomica en transaccion.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['policies'],
        properties: [
            new OA\Property(property: 'policies', type: 'array', items: new OA\Items(
                required: ['day', 'percentage'],
                properties: [
                    new OA\Property(property: 'id',          type: 'integer', nullable: true, description: 'Omitir para crear'),
                    new OA\Property(property: 'day',         type: 'integer', example: 8,     description: 'Dias transcurridos'),
                    new OA\Property(property: 'conditional', type: 'string',  nullable: true, description: 'Condicion opcional'),
                    new OA\Property(property: 'percentage',  type: 'number',  example: 10.5,  description: 'Porcentaje a cobrar 0-100'),
                ]
            )),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Politicas guardadas'),
        new OA\Response(response: 422, description: 'Datos invalidos'),
    ]
)]
#[OA\Get(path: '/charge-policies/{id}', operationId: 'chargePoliciesShow', tags: ['ChargePolicies'], summary: 'Obtener politica por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Politica de cargo'),
        new OA\Response(response: 404, description: 'No encontrada'),
    ]
)]
#[OA\Post(path: '/charge-policies', operationId: 'chargePoliciesStore', tags: ['ChargePolicies'], summary: 'Crear politica individual',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['day', 'percentage'],
        properties: [
            new OA\Property(property: 'day',         type: 'integer', example: 30),
            new OA\Property(property: 'conditional', type: 'string',  nullable: true),
            new OA\Property(property: 'percentage',  type: 'number',  example: 25.0),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Politica creada')]
)]
#[OA\Put(path: '/charge-policies/{id}', operationId: 'chargePoliciesUpdate', tags: ['ChargePolicies'], summary: 'Actualizar politica',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'day',         type: 'integer'),
            new OA\Property(property: 'conditional', type: 'string',  nullable: true),
            new OA\Property(property: 'percentage',  type: 'number'),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Politica actualizada'),
        new OA\Response(response: 404, description: 'No encontrada'),
    ]
)]
#[OA\Delete(path: '/charge-policies/{id}', operationId: 'chargePoliciesDestroy', tags: ['ChargePolicies'], summary: 'Eliminar politica (soft delete)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Politica eliminada'),
        new OA\Response(response: 404, description: 'No encontrada'),
    ]
)]
class ChargePolicyDocs
{
}
