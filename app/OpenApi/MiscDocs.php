<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Customers ─────────────────────────────────────────────────────────────────
#[OA\Get(path: '/customers', operationId: 'customersList', tags: ['Customers'], summary: 'Listar clientes',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string')),
    ],
    responses: [new OA\Response(response: 200, description: 'Lista de clientes')]
)]
#[OA\Get(path: '/customers/search', operationId: 'customersSearch', tags: ['Customers'], summary: 'Buscar clientes por nombre',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'search', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
    responses: [new OA\Response(response: 200, description: 'Clientes encontrados')]
)]
#[OA\Get(path: '/customers/{id}', operationId: 'customersShow', tags: ['Customers'], summary: 'Obtener cliente por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Cliente'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/customers', operationId: 'customersStore', tags: ['Customers'], summary: 'Crear cliente',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['idCliente', 'razonSocial'],
        properties: [
            new OA\Property(property: 'idCliente',   type: 'integer'),
            new OA\Property(property: 'razonSocial', type: 'string'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Cliente creado')]
)]
#[OA\Post(path: '/customers/saveLocal', operationId: 'customersStoreLocal', tags: ['Customers'], summary: 'Guardar cliente en tabla local',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['idCliente', 'razonSocial'],
        properties: [
            new OA\Property(property: 'idCliente',   type: 'integer'),
            new OA\Property(property: 'razonSocial', type: 'string'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Cliente creado localmente')]
)]
#[OA\Put(path: '/customers/{id}', operationId: 'customersUpdate', tags: ['Customers'], summary: 'Actualizar cliente',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'razonSocial', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Cliente actualizado')]
)]
#[OA\Delete(path: '/customers/{id}', operationId: 'customersDestroy', tags: ['Customers'], summary: 'Eliminar cliente (soft delete)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Cliente eliminado')]
)]
// ── Notifications ─────────────────────────────────────────────────────────────
#[OA\Get(path: '/notifications', operationId: 'notificationsList', tags: ['Notifications'], summary: 'Notificaciones del usuario',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Notificaciones')]
)]
#[OA\Get(path: '/notifications/unread', operationId: 'notificationsUnread', tags: ['Notifications'], summary: 'Notificaciones no leidas',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Notificaciones no leidas')]
)]
#[OA\Patch(path: '/notifications/{id}/read', operationId: 'notificationsMarkRead', tags: ['Notifications'], summary: 'Marcar notificacion como leida',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Notificacion marcada como leida'),
        new OA\Response(response: 404, description: 'No encontrada'),
    ]
)]
// ── Dashboard ─────────────────────────────────────────────────────────────────
#[OA\Get(path: '/dashboard', operationId: 'dashboardMetrics', tags: ['Dashboard'], summary: 'Metricas de solicitudes por periodo',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'days',            in: 'query', description: 'Ultimos N dias (ej. 30)',          schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'from',            in: 'query', description: 'Fecha inicio YYYY-MM-DD',          schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'to',              in: 'query', description: 'Fecha fin YYYY-MM-DD',             schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'request_type_id', in: 'query', description: 'Filtrar por tipo de solicitud',   schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Series y totales por periodo')]
)]
// ── Security ──────────────────────────────────────────────────────────────────
#[OA\Get(path: '/security/users/blocked', operationId: 'securityBlockedUsers', tags: ['Security'], summary: 'Usuarios bloqueados',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Usuarios bloqueados')]
)]
#[OA\Get(path: '/security/ips/blocked', operationId: 'securityBlockedIps', tags: ['Security'], summary: 'IPs bloqueadas',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'IPs bloqueadas')]
)]
#[OA\Post(path: '/security/users/{id}/unlock', operationId: 'securityUnlockUser', tags: ['Security'], summary: 'Desbloquear usuario',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Usuario desbloqueado')]
)]
#[OA\Post(path: '/security/ips/unlock', operationId: 'securityUnlockIp', tags: ['Security'], summary: 'Desbloquear IP',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['ip'],
        properties: [new OA\Property(property: 'ip', type: 'string', example: '192.168.1.1')]
    )),
    responses: [new OA\Response(response: 200, description: 'IP desbloqueada')]
)]
#[OA\Get(path: '/security/login-attempt-settings', operationId: 'securityLoginSettings', tags: ['Security'], summary: 'Configuracion de intentos de login',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Configuracion')]
)]
#[OA\Put(path: '/security/login-attempt-settings', operationId: 'securityUpdateLoginSettings', tags: ['Security'], summary: 'Actualizar configuracion de intentos de login',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['maxUserAttempts', 'maxIpAttempts', 'sessionTimeoutMinutes'],
        properties: [
            new OA\Property(property: 'maxUserAttempts',       type: 'integer', example: 5),
            new OA\Property(property: 'maxIpAttempts',         type: 'integer', example: 10),
            new OA\Property(property: 'sessionTimeoutMinutes', type: 'integer', example: 120),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Configuracion actualizada')]
)]
// ── Password Requirements ─────────────────────────────────────────────────────
#[OA\Post(path: '/password-requirements/validate', operationId: 'passwordValidate', tags: ['PasswordRequirements'], summary: 'Validar contrasena (publica)',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['password'],
        properties: [new OA\Property(property: 'password', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de validacion')]
)]
#[OA\Get(path: '/password-requirements', operationId: 'passwordRequirements', tags: ['PasswordRequirements'], summary: 'Requisitos de contrasena',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Requisitos')]
)]
#[OA\Get(path: '/password-requirements/formatted', operationId: 'passwordRequirementsFormatted', tags: ['PasswordRequirements'], summary: 'Requisitos formateados para UI',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Requisitos formateados')]
)]
#[OA\Put(path: '/password-requirements', operationId: 'passwordRequirementsUpdate', tags: ['PasswordRequirements'], summary: 'Actualizar requisitos (admin)',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'minLength',        type: 'integer'),
            new OA\Property(property: 'requireUppercase', type: 'boolean'),
            new OA\Property(property: 'requireNumbers',   type: 'boolean'),
            new OA\Property(property: 'requireSymbols',   type: 'boolean'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Requisitos actualizados')]
)]
class MiscDocs
{
}
