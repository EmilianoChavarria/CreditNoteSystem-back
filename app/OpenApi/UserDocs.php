<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Users ─────────────────────────────────────────────────────────────────────
#[OA\Get(path: '/users', operationId: 'usersList', tags: ['Users'], summary: 'Listar todos los usuarios',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de usuarios', content: new OA\JsonContent(
        properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User'))]
    ))]
)]
#[OA\Get(path: '/usersPag', operationId: 'usersPaginated', tags: ['Users'], summary: 'Usuarios paginados con filtros',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'search',   in: 'query', description: 'Busca por nombre o email', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'roleName', in: 'query', description: 'Filtra por roleName exacto. Omitir o enviar "All" para todos.',
            schema: new OA\Schema(type: 'string', example: 'ADMIN')),
    ],
    responses: [new OA\Response(response: 200, description: 'Usuarios paginados')]
)]
#[OA\Get(path: '/users/managers', operationId: 'usersManagers', tags: ['Users'], summary: 'Usuarios con rol de ventas o gerencia',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de managers')]
)]
#[OA\Get(path: '/users/me', operationId: 'usersMe', tags: ['Users'], summary: 'Perfil del usuario autenticado',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Datos del usuario actual', content: new OA\JsonContent(ref: '#/components/schemas/User'))]
)]
#[OA\Patch(path: '/users/me/password', operationId: 'usersChangeOwnPassword', tags: ['Users'], summary: 'Cambiar contrasena propia',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['currentPassword', 'newPassword'],
        properties: [
            new OA\Property(property: 'currentPassword', type: 'string', example: 'OldP@ss1'),
            new OA\Property(property: 'newPassword',     type: 'string', example: 'NewP@ss2024!'),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Contrasena actualizada'),
        new OA\Response(response: 422, description: 'Contrasena actual incorrecta o nueva no cumple requisitos'),
    ]
)]
#[OA\Patch(path: '/users/{id}/password', operationId: 'usersChangePasswordById', tags: ['Users'], summary: 'Cambiar contrasena de otro usuario (admin)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['newPassword'],
        properties: [new OA\Property(property: 'newPassword', type: 'string', example: 'TempP@ss2024!')]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Contrasena actualizada'),
        new OA\Response(response: 404, description: 'Usuario no encontrado'),
    ]
)]
#[OA\Post(path: '/users/{id}/resend-welcome-email', operationId: 'usersResendWelcome', tags: ['Users'],
    summary: 'Reenviar email de bienvenida a un usuario',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Email reenviado'),
        new OA\Response(response: 404, description: 'Usuario no encontrado'),
    ]
)]
#[OA\Get(path: '/users/{id}', operationId: 'usersShow', tags: ['Users'], summary: 'Obtener usuario por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del usuario', content: new OA\JsonContent(ref: '#/components/schemas/User')),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/users', operationId: 'usersStore', tags: ['Users'], summary: 'Crear usuario',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['fullName', 'email', 'password', 'roleId'],
        properties: [
            new OA\Property(property: 'fullName',          type: 'string',  example: 'Maria Lopez'),
            new OA\Property(property: 'email',             type: 'string',  format: 'email', example: 'maria@empresa.com'),
            new OA\Property(property: 'password',          type: 'string',  example: 'P@ssw0rd!'),
            new OA\Property(property: 'roleId',            type: 'integer', example: 2),
            new OA\Property(property: 'supervisorId',      type: 'integer', nullable: true, description: 'ID del supervisor directo'),
            new OA\Property(property: 'clientId',          type: 'string',  nullable: true),
            new OA\Property(property: 'preferredLanguage', type: 'string',  nullable: true, enum: ['en', 'es'], example: 'es'),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Usuario creado', content: new OA\JsonContent(ref: '#/components/schemas/User')),
        new OA\Response(response: 422, description: 'Datos invalidos o email duplicado'),
    ]
)]
#[OA\Put(path: '/users/{id}', operationId: 'usersUpdate', tags: ['Users'], summary: 'Actualizar usuario',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'fullName',     type: 'string',  nullable: true),
            new OA\Property(property: 'roleId',       type: 'integer', nullable: true),
            new OA\Property(property: 'supervisorId', type: 'integer', nullable: true),
            new OA\Property(property: 'clientId',     type: 'string',  nullable: true),
            new OA\Property(property: 'isActive',     type: 'boolean', nullable: true),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Usuario actualizado'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Delete(path: '/users/{id}', operationId: 'usersDestroy', tags: ['Users'], summary: 'Eliminar usuario (soft delete)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Usuario eliminado'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
// ── User Assignments ──────────────────────────────────────────────────────────
#[OA\Get(path: '/users/assignment/leaders', operationId: 'assignmentLeaders', tags: ['UserAssignments'],
    summary: 'Listar usuarios con rol CS Leader disponibles para asignar',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de lideres')]
)]
#[OA\Get(path: '/users/assignment/assignable-users', operationId: 'assignableUsers', tags: ['UserAssignments'],
    summary: 'Listar usuarios que pueden ser asignados a un lider',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de usuarios asignables')]
)]
#[OA\Get(path: '/users/{leaderUserId}/assignments', operationId: 'assignmentsIndex', tags: ['UserAssignments'],
    summary: 'Obtener asignaciones de un CS Leader',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'leaderUserId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Usuarios asignados al lider')]
)]
#[OA\Put(path: '/users/assignments', operationId: 'assignmentsUpsert', tags: ['UserAssignments'],
    summary: 'Crear o actualizar asignaciones de un CS Leader (upsert)',
    description: 'Reemplaza todas las asignaciones del lider con los IDs enviados.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['leaderUserId', 'assignedUserIds'],
        properties: [
            new OA\Property(property: 'leaderUserId',    type: 'integer', example: 10),
            new OA\Property(property: 'assignedUserIds', type: 'array', items: new OA\Items(type: 'integer'), example: [3, 7, 12]),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Asignaciones guardadas')]
)]
#[OA\Delete(path: '/users/{leaderUserId}/assignments/{assignedUserId}', operationId: 'assignmentsDestroy', tags: ['UserAssignments'],
    summary: 'Eliminar asignacion entre lider y usuario',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'leaderUserId',   in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'assignedUserId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Asignacion eliminada')]
)]
class UserDocs
{
}
