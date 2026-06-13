<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Customers ─────────────────────────────────────────────────────────────────
#[OA\Get(path: '/customers', operationId: 'customersList', tags: ['Customers'], summary: 'Listar clientes paginados',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string')),
    ],
    responses: [new OA\Response(response: 200, description: 'Lista de clientes')]
)]
#[OA\Get(path: '/customers/search', operationId: 'customersSearch', tags: ['Customers'], summary: 'Buscar clientes por razon social',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'search', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'LAMINADORA'))],
    responses: [new OA\Response(response: 200, description: 'Clientes encontrados')]
)]
#[OA\Get(path: '/customers/{id}', operationId: 'customersShow', tags: ['Customers'], summary: 'Obtener cliente por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del cliente'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/customers', operationId: 'customersStore', tags: ['Customers'], summary: 'Crear cliente',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['idCliente', 'razonSocial'],
        properties: [
            new OA\Property(property: 'idCliente',   type: 'integer', example: 121693),
            new OA\Property(property: 'razonSocial', type: 'string',  example: 'ACME SA DE CV'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Cliente creado')]
)]
#[OA\Post(path: '/customers/saveLocal', operationId: 'customersStoreLocal', tags: ['Customers'], summary: 'Guardar cliente en tabla local del sistema',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['idCliente', 'razonSocial'],
        properties: [
            new OA\Property(property: 'idCliente',   type: 'integer', example: 121693),
            new OA\Property(property: 'razonSocial', type: 'string',  example: 'ACME SA DE CV'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Cliente guardado localmente')]
)]
#[OA\Put(path: '/customers/{id}', operationId: 'customersUpdate', tags: ['Customers'], summary: 'Actualizar datos de cliente',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'razonSocial', type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Cliente actualizado')]
)]
#[OA\Delete(path: '/customers/{id}', operationId: 'customersDestroy', tags: ['Customers'], summary: 'Eliminar cliente (soft delete)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Cliente eliminado')]
)]
// ── Notifications ─────────────────────────────────────────────────────────────
#[OA\Get(path: '/notifications', operationId: 'notificationsList', tags: ['Notifications'], summary: 'Notificaciones del usuario autenticado',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Notificaciones paginadas')]
)]
#[OA\Get(path: '/notifications/unread', operationId: 'notificationsUnread', tags: ['Notifications'], summary: 'Contador de notificaciones no leidas',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Total de no leidas')]
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
#[OA\Get(path: '/dashboard', operationId: 'dashboardMetrics', tags: ['Dashboard'],
    summary: 'Metricas de solicitudes por periodo',
    description: 'Retorna totales, series por fecha y distribuciones. Puede filtrar por rango de fechas o por N ultimos dias.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'days',            in: 'query', description: 'Ultimos N dias (ej. 30). Ignorado si se envia from/to.',
            schema: new OA\Schema(type: 'integer', example: 30)),
        new OA\Parameter(name: 'from',            in: 'query', description: 'Fecha inicio YYYY-MM-DD',
            schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01')),
        new OA\Parameter(name: 'to',              in: 'query', description: 'Fecha fin YYYY-MM-DD',
            schema: new OA\Schema(type: 'string', format: 'date', example: '2026-06-01')),
        new OA\Parameter(name: 'request_type_id', in: 'query', description: 'Filtrar por tipo de solicitud',
            schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Series temporales y totales por periodo y estado')]
)]
// ── Security ──────────────────────────────────────────────────────────────────
#[OA\Get(path: '/security/users/blocked', operationId: 'securityBlockedUsers', tags: ['Security'], summary: 'Listar usuarios bloqueados por intentos fallidos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Usuarios bloqueados')]
)]
#[OA\Get(path: '/security/ips/blocked', operationId: 'securityBlockedIps', tags: ['Security'], summary: 'Listar IPs bloqueadas',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'IPs bloqueadas')]
)]
#[OA\Post(path: '/security/users/{id}/unlock', operationId: 'securityUnlockUser', tags: ['Security'], summary: 'Desbloquear usuario manualmente',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Usuario desbloqueado')]
)]
#[OA\Post(path: '/security/ips/unlock', operationId: 'securityUnlockIp', tags: ['Security'], summary: 'Desbloquear IP manualmente',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['ip'],
        properties: [new OA\Property(property: 'ip', type: 'string', format: 'ipv4', example: '192.168.1.100')]
    )),
    responses: [new OA\Response(response: 200, description: 'IP desbloqueada')]
)]
#[OA\Get(path: '/security/login-attempt-settings', operationId: 'securityLoginSettings', tags: ['Security'], summary: 'Configuracion de intentos de login',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Umbrales y tiempos de bloqueo actuales')]
)]
#[OA\Put(path: '/security/login-attempt-settings', operationId: 'securityUpdateLoginSettings', tags: ['Security'], summary: 'Actualizar configuracion de intentos de login',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['maxUserAttempts', 'maxIpAttempts', 'sessionTimeoutMinutes'],
        properties: [
            new OA\Property(property: 'maxUserAttempts',       type: 'integer', example: 5,   description: 'Intentos fallidos antes de bloquear usuario'),
            new OA\Property(property: 'maxIpAttempts',         type: 'integer', example: 10,  description: 'Intentos fallidos antes de bloquear IP'),
            new OA\Property(property: 'sessionTimeoutMinutes', type: 'integer', example: 120, description: 'Minutos de inactividad antes de expirar sesion'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Configuracion actualizada')]
)]
// ── Password Requirements ─────────────────────────────────────────────────────
#[OA\Post(path: '/password-requirements/validate', operationId: 'passwordValidate', tags: ['PasswordRequirements'],
    summary: 'Validar una contrasena contra los requisitos activos (endpoint publico)',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['password'],
        properties: [new OA\Property(property: 'password', type: 'string', example: 'MyP@ss2024')]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de validacion con detalle de reglas')]
)]
#[OA\Get(path: '/password-requirements', operationId: 'passwordRequirements', tags: ['PasswordRequirements'], summary: 'Obtener requisitos de contrasena activos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Objeto de requisitos')]
)]
#[OA\Get(path: '/password-requirements/formatted', operationId: 'passwordRequirementsFormatted', tags: ['PasswordRequirements'],
    summary: 'Obtener requisitos formateados para mostrar en UI',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de reglas con texto descriptivo')]
)]
#[OA\Put(path: '/password-requirements', operationId: 'passwordRequirementsUpdate', tags: ['PasswordRequirements'], summary: 'Actualizar requisitos de contrasena (admin)',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'minLength',        type: 'integer', example: 8),
            new OA\Property(property: 'requireUppercase', type: 'boolean', example: true),
            new OA\Property(property: 'requireNumbers',   type: 'boolean', example: true),
            new OA\Property(property: 'requireSymbols',   type: 'boolean', example: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Requisitos actualizados')]
)]
// ── Charge Types ──────────────────────────────────────────────────────────────
#[OA\Get(path: '/charge-types', operationId: 'chargeTypesList', tags: ['ChargeTypes'], summary: 'Listar tipos de cargo disponibles',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Catalogo de tipos de cargo')]
)]
// ── Email Config ──────────────────────────────────────────────────────────────
#[OA\Get(path: '/email-config', operationId: 'emailConfigShow', tags: ['EmailConfig'], summary: 'Obtener configuracion de email del sistema',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Configuracion de email actual')]
)]
#[OA\Put(path: '/email-config', operationId: 'emailConfigUpdate', tags: ['EmailConfig'], summary: 'Crear o actualizar configuracion de email (upsert)',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'driver',       type: 'string',  nullable: true, example: 'sendgrid'),
            new OA\Property(property: 'fromAddress',  type: 'string',  nullable: true, format: 'email', example: 'no-reply@empresa.com'),
            new OA\Property(property: 'fromName',     type: 'string',  nullable: true, example: 'Sistema Notas de Credito'),
            new OA\Property(property: 'apiKey',       type: 'string',  nullable: true, example: 'SG.xxxxx'),
            new OA\Property(property: 'emailMode',    type: 'string',  nullable: true, enum: ['sendgrid', 'smtp', 'disabled'], example: 'sendgrid'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Configuracion guardada')]
)]
// ── Exports ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/exports/excel', operationId: 'exportsExcel', tags: ['Exports'],
    summary: 'Exportar solicitudes a Excel',
    description: 'Genera y descarga un archivo .xlsx con los datos de solicitudes segun los filtros aplicados.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'requestTypeId', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'from',          in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'to',            in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'status',        in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected', 'cancelled'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Archivo Excel', content: new OA\MediaType(
            mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        )),
    ]
)]
class MiscDocs
{
}
