<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Listing & Search ──────────────────────────────────────────────────────────
#[OA\Get(path: '/requests', operationId: 'requestsList', tags: ['Requests'], summary: 'Listar todas las solicitudes',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de solicitudes')]
)]
#[OA\Get(path: '/requests/requesters', operationId: 'requestsRequesters', tags: ['Requests'], summary: 'Usuarios que han creado solicitudes',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de solicitantes')]
)]
#[OA\Get(path: '/requests/drafts', operationId: 'requestsDrafts', tags: ['Requests'], summary: 'Borradores del usuario autenticado',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Borradores')]
)]
#[OA\Get(path: '/requests/pending/me', operationId: 'requestsPendingMe', tags: ['Requests'], summary: 'Solicitudes pendientes del usuario actual',
    description: 'Retorna solicitudes cuyo paso actual esta asignado al usuario autenticado o a su rol broadcast (REPLENISHMENT, WAREHOUSE, IT).',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'requestTypeId', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'per_page',      in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'perPage',       in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',          in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'search',        in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'roleName',      in: 'query', description: 'Filtra por roleName del paso actual. Omitir o enviar "All" para todos.',
            schema: new OA\Schema(type: 'string', example: 'ADMIN')),
    ],
    responses: [new OA\Response(response: 200, description: 'Pendientes propias')]
)]
#[OA\Get(path: '/requests/pending/{id}', operationId: 'requestsPendingByRole', tags: ['Requests'], summary: 'Solicitudes pendientes por rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'roleId', schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Pendientes del rol')]
)]
#[OA\Get(path: '/requests/reasons/{requestTypeId}', operationId: 'requestsReasonsByType', tags: ['Requests'], summary: 'Razones de rechazo por tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Lista de razones')]
)]
#[OA\Get(path: '/requests/next-number/{requestTypeId}', operationId: 'requestsNextNumber', tags: ['Requests'], summary: 'Siguiente numero de solicitud para el tipo dado',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Siguiente numero')]
)]
#[OA\Get(path: '/requests/customer/{customerId}', operationId: 'requestsByCustomer', tags: ['Requests'], summary: 'Solicitudes de un cliente especifico',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'customerId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'per_page',   in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',       in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Solicitudes del cliente')]
)]
#[OA\Get(path: '/requests/{id}', operationId: 'requestsByType', tags: ['Requests'], summary: 'Solicitudes paginadas por tipo (requestTypeId)',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id',       in: 'path', required: true, description: 'requestTypeId', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'roleName', in: 'query', description: 'Filtra por roleName del paso actual. Omitir o enviar "All" para todos.',
            schema: new OA\Schema(type: 'string', example: 'ADMIN')),
    ],
    responses: [new OA\Response(response: 200, description: 'Solicitudes paginadas del tipo')]
)]
// ── Attachments ───────────────────────────────────────────────────────────────
#[OA\Get(path: '/requests/{requestId}/history', operationId: 'requestsHistory', tags: ['Requests'], summary: 'Historial de flujo de una solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Historial de acciones del workflow')]
)]
#[OA\Get(path: '/requests/{requestId}/pdf', operationId: 'requestsDownloadPdf', tags: ['Requests'], summary: 'Descargar PDF de una solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'PDF de la solicitud', content: new OA\MediaType(mediaType: 'application/pdf')),
        new OA\Response(response: 404, description: 'Solicitud no encontrada'),
    ]
)]
#[OA\Get(path: '/requests/{requestId}/attachments', operationId: 'requestsAttachments', tags: ['Requests'], summary: 'Archivos adjuntos de una solicitud',
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
#[OA\Get(path: '/requests/attachments/{attachmentId}/preview-link', operationId: 'requestsAttachmentPreviewLink', tags: ['Requests'], summary: 'URL firmada para preview del adjunto',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'URL firmada')]
)]
#[OA\Delete(path: '/requests/{requestId}/attachments/{attachmentId}', operationId: 'requestsAttachmentDelete', tags: ['Requests'], summary: 'Eliminar adjunto de una solicitud',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'requestId',    in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Adjunto eliminado')]
)]
// ── Create / Update / Draft ───────────────────────────────────────────────────
#[OA\Post(path: '/requests/draft', operationId: 'requestsSaveDraft', tags: ['Requests'], summary: 'Guardar borrador de solicitud',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestTypeId'],
        properties: [
            new OA\Property(property: 'requestTypeId',    type: 'integer',  example: 1),
            new OA\Property(property: 'classificationId', type: 'integer',  nullable: true),
            new OA\Property(property: 'clientId',         type: 'string',   nullable: true),
            new OA\Property(property: 'notes',            type: 'string',   nullable: true),
            new OA\Property(property: 'reasonId',         type: 'integer',  nullable: true),
            new OA\Property(property: 'totalAmount',      type: 'number',   nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Borrador guardado', content: new OA\JsonContent(ref: '#/components/schemas/Request'))]
)]
#[OA\Delete(path: '/requests/drafts/{id}', operationId: 'requestsDeleteDraft', tags: ['Requests'], summary: 'Eliminar borrador',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Borrador eliminado'),
        new OA\Response(response: 403, description: 'No autorizado'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/requests/newRequest', operationId: 'requestsCreate', tags: ['Requests'], summary: 'Crear solicitud formal e iniciar flujo de aprobacion',
    description: 'Crea la solicitud, le asigna numero correlativo y la enruta al primer paso del workflow configurado para su tipo y clasificacion.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestTypeId', 'classificationId'],
        properties: [
            new OA\Property(property: 'requestTypeId',    type: 'integer', example: 1),
            new OA\Property(property: 'classificationId', type: 'integer', example: 3),
            new OA\Property(property: 'clientId',         type: 'string',  nullable: true, example: '121693'),
            new OA\Property(property: 'notes',            type: 'string',  nullable: true),
            new OA\Property(property: 'reasonId',         type: 'integer', nullable: true),
            new OA\Property(property: 'totalAmount',      type: 'number',  nullable: true, example: 50000.00),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Solicitud creada y asignada al workflow', content: new OA\JsonContent(ref: '#/components/schemas/Request')),
        new OA\Response(response: 422, description: 'Datos invalidos o workflow no configurado'),
    ]
)]
#[OA\Put(path: '/requests/{requestId}', operationId: 'requestsUpdate', tags: ['Requests'], summary: 'Actualizar solicitud (solo campos editables)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'notes',       type: 'string', nullable: true),
            new OA\Property(property: 'totalAmount', type: 'number', nullable: true),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Solicitud actualizada'),
        new OA\Response(response: 403, description: 'Sin permisos para editar'),
        new OA\Response(response: 422, description: 'La solicitud no es editable en su estado actual'),
    ]
)]
// ── Workflow Actions (single) ──────────────────────────────────────────────────
#[OA\Post(path: '/requests/{requestId}/approve', operationId: 'requestsApprove', tags: ['Requests'], summary: 'Aprobar solicitud en el paso actual',
    description: 'Solo puede aprobar: admin, usuario asignado al paso, o cualquier usuario de un rol broadcast (REPLENISHMENT, WAREHOUSE, IT).',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Aprobado sin observaciones')]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Solicitud aprobada o avanzada al siguiente paso'),
        new OA\Response(response: 403, description: 'Sin permisos para aprobar este paso'),
        new OA\Response(response: 422, description: 'No se puede aprobar (estado invalido, no hay siguiente usuario disponible)'),
    ]
)]
#[OA\Post(path: '/requests/{requestId}/reject', operationId: 'requestsReject', tags: ['Requests'], summary: 'Rechazar solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['reasonId'],
        properties: [
            new OA\Property(property: 'reasonId', type: 'integer', example: 2),
            new OA\Property(property: 'comment',  type: 'string',  nullable: true),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Solicitud rechazada'),
        new OA\Response(response: 403, description: 'Sin permisos'),
        new OA\Response(response: 422, description: 'No se puede rechazar en su estado actual'),
    ]
)]
#[OA\Post(path: '/requests/{requestId}/cancel', operationId: 'requestsCancel', tags: ['Requests'], summary: 'Cancelar solicitud',
    description: 'Accion irreversible. El estado pasa a "cancelled".',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'comments', type: 'string', nullable: true)]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Solicitud cancelada'),
        new OA\Response(response: 422, description: 'No se puede cancelar en su estado actual'),
    ]
)]
#[OA\Post(path: '/requests/{requestId}/send-back', operationId: 'requestsSendBack', tags: ['Requests'], summary: 'Regresar solicitud a un paso anterior',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['targetWorkflowStepId'],
        properties: [
            new OA\Property(property: 'targetWorkflowStepId', type: 'integer', example: 5, description: 'ID del paso al que se regresa'),
            new OA\Property(property: 'comments',             type: 'string',  nullable: true),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Solicitud regresada al paso indicado'),
        new OA\Response(response: 403, description: 'Sin permisos'),
        new OA\Response(response: 422, description: 'Paso destino invalido'),
    ]
)]
// ── Workflow Actions (mass) ───────────────────────────────────────────────────
#[OA\Post(path: '/requests/approve-mass', operationId: 'requestsApproveMass', tags: ['Requests'], summary: 'Aprobar multiples solicitudes',
    description: 'Procesa cada solicitud de forma independiente. Retorna un resumen de exitos y errores por ID.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds'],
        properties: [
            new OA\Property(property: 'requestIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
            new OA\Property(property: 'comment',    type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de aprobacion masiva con detalle por solicitud')]
)]
#[OA\Post(path: '/requests/reject-mass', operationId: 'requestsRejectMass', tags: ['Requests'], summary: 'Rechazar multiples solicitudes',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds', 'reasonId'],
        properties: [
            new OA\Property(property: 'requestIds', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5]),
            new OA\Property(property: 'reasonId',   type: 'integer', example: 2),
            new OA\Property(property: 'comment',    type: 'string',  nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de rechazo masivo con detalle por solicitud')]
)]
#[OA\Post(path: '/requests/cancel-mass', operationId: 'requestsCancelMass', tags: ['Requests'], summary: 'Cancelar multiples solicitudes',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds'],
        properties: [
            new OA\Property(property: 'requestIds', type: 'array', items: new OA\Items(type: 'integer'), example: [6, 7]),
            new OA\Property(property: 'comments',   type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de cancelacion masiva')]
)]
#[OA\Post(path: '/requests/send-back-mass', operationId: 'requestsSendBackMass', tags: ['Requests'], summary: 'Regresar multiples solicitudes al mismo paso anterior',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['requestIds', 'targetWorkflowStepId'],
        properties: [
            new OA\Property(property: 'requestIds',           type: 'array',   items: new OA\Items(type: 'integer'), example: [8, 9]),
            new OA\Property(property: 'targetWorkflowStepId', type: 'integer', example: 5),
            new OA\Property(property: 'comments',             type: 'string',  nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de regreso masivo')]
)]
class RequestDocs
{
}
