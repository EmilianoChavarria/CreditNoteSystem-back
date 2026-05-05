<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/requests', operationId: 'requestsList', tags: ['Requests'], summary: 'Listar todas las solicitudes',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de solicitudes')]
)]
#[OA\Get(path: '/requests/drafts', operationId: 'requestsDrafts', tags: ['Requests'], summary: 'Borradores del usuario autenticado',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Borradores')]
)]
#[OA\Get(path: '/requests/pending/me', operationId: 'requestsPendingMe', tags: ['Requests'], summary: 'Solicitudes pendientes del usuario actual',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Pendientes propias')]
)]
#[OA\Get(path: '/requests/pending/{id}', operationId: 'requestsPendingByRole', tags: ['Requests'], summary: 'Solicitudes pendientes por rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Pendientes del rol')]
)]
#[OA\Get(path: '/requests/reasons', operationId: 'requestsReasons', tags: ['Requests'], summary: 'Listar razones de solicitud',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de razones')]
)]
#[OA\Get(path: '/requests/next-number/{requestTypeId}', operationId: 'requestsNextNumber', tags: ['Requests'], summary: 'Siguiente numero de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Siguiente numero')]
)]
#[OA\Get(path: '/requests/{id}', operationId: 'requestsByType', tags: ['Requests'], summary: 'Solicitudes por tipo',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'requestTypeId', schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Solicitudes del tipo')]
)]
#[OA\Get(path: '/requests/{requestId}/history', operationId: 'requestsHistory', tags: ['Requests'], summary: 'Historial de una solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Historial')]
)]
#[OA\Get(path: '/requests/{requestId}/attachments', operationId: 'requestsAttachments', tags: ['Requests'], summary: 'Adjuntos de una solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Lista de adjuntos')]
)]
#[OA\Get(path: '/requests/attachments/{attachmentId}', operationId: 'requestsAttachmentShow', tags: ['Requests'], summary: 'Obtener adjunto por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Adjunto'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Get(path: '/requests/attachments/{attachmentId}/preview-link', operationId: 'requestsAttachmentPreviewLink', tags: ['Requests'], summary: 'URL firmada de preview del adjunto',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'URL firmada')]
)]
#[OA\Delete(path: '/requests/{requestId}/attachments/{attachmentId}', operationId: 'requestsAttachmentDelete', tags: ['Requests'], summary: 'Eliminar adjunto de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'requestId',    in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Adjunto eliminado')]
)]
#[OA\Post(path: '/requests/draft', operationId: 'requestsSaveDraft', tags: ['Requests'], summary: 'Guardar borrador',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestTypeId'],
        properties: [
            new OA\Property(property: 'requestTypeId',    type: 'integer'),
            new OA\Property(property: 'classificationId', type: 'integer', nullable: true),
            new OA\Property(property: 'clientId',         type: 'string',  nullable: true),
            new OA\Property(property: 'notes',            type: 'string',  nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Borrador guardado')]
)]
#[OA\Post(path: '/requests/newRequest', operationId: 'requestsCreate', tags: ['Requests'], summary: 'Crear solicitud formal',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestTypeId', 'classificationId'],
        properties: [
            new OA\Property(property: 'requestTypeId',    type: 'integer'),
            new OA\Property(property: 'classificationId', type: 'integer'),
            new OA\Property(property: 'clientId',         type: 'string',  nullable: true),
            new OA\Property(property: 'notes',            type: 'string',  nullable: true),
            new OA\Property(property: 'reasonId',         type: 'integer', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Solicitud creada')]
)]
#[OA\Put(path: '/requests/{requestId}', operationId: 'requestsUpdate', tags: ['Requests'], summary: 'Actualizar solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'notes', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Solicitud actualizada')]
)]
#[OA\Post(path: '/requests/{requestId}/approve', operationId: 'requestsApprove', tags: ['Requests'], summary: 'Aprobar solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'comment', type: 'string', nullable: true)]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Solicitud aprobada'),
        new OA\Response(response: 422, description: 'No se puede aprobar'),
    ]
)]
#[OA\Post(path: '/requests/{requestId}/reject', operationId: 'requestsReject', tags: ['Requests'], summary: 'Rechazar solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['reasonId'],
        properties: [
            new OA\Property(property: 'reasonId', type: 'integer'),
            new OA\Property(property: 'comment',  type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Solicitud rechazada')]
)]
#[OA\Post(path: '/requests/approve-mass', operationId: 'requestsApproveMass', tags: ['Requests'], summary: 'Aprobar multiples solicitudes',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds'],
        properties: [new OA\Property(property: 'requestIds', type: 'array', items: new OA\Items(type: 'integer'))]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de aprobacion masiva')]
)]
#[OA\Post(path: '/requests/reject-mass', operationId: 'requestsRejectMass', tags: ['Requests'], summary: 'Rechazar multiples solicitudes',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds', 'reasonId'],
        properties: [
            new OA\Property(property: 'requestIds', type: 'array', items: new OA\Items(type: 'integer')),
            new OA\Property(property: 'reasonId',   type: 'integer'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de rechazo masivo')]
)]
class RequestDocs
{
}
