<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(path: '/users', operationId: 'usersList', tags: ['Users'], summary: 'Listar todos los usuarios',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de usuarios')]
)]
#[OA\Get(path: '/usersPag', operationId: 'usersPaginated', tags: ['Users'], summary: 'Usuarios paginados',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [new OA\Response(response: 200, description: 'Usuarios paginados')]
)]
#[OA\Get(path: '/users/managers', operationId: 'usersManagers', tags: ['Users'], summary: 'Usuarios con rol de ventas o gerencia',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de managers')]
)]
#[OA\Get(path: '/users/me', operationId: 'usersMe', tags: ['Users'], summary: 'Perfil del usuario autenticado',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Datos del usuario actual')]
)]
#[OA\Patch(path: '/users/me/password', operationId: 'usersChangeOwnPassword', tags: ['Users'], summary: 'Cambiar contrasena propia',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['currentPassword', 'newPassword'],
        properties: [
            new OA\Property(property: 'currentPassword', type: 'string'),
            new OA\Property(property: 'newPassword',     type: 'string'),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Contrasena actualizada'),
        new OA\Response(response: 422, description: 'Contrasena invalida'),
    ]
)]
#[OA\Patch(path: '/users/{id}/password', operationId: 'usersChangePasswordById', tags: ['Users'], summary: 'Cambiar contrasena de otro usuario (admin)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['newPassword'],
        properties: [new OA\Property(property: 'newPassword', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Contrasena actualizada')]
)]
#[OA\Get(path: '/users/{id}', operationId: 'usersShow', tags: ['Users'], summary: 'Obtener usuario por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del usuario'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Post(path: '/users', operationId: 'usersStore', tags: ['Users'], summary: 'Crear usuario',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['fullName', 'email', 'password', 'roleId'],
        properties: [
            new OA\Property(property: 'fullName', type: 'string'),
            new OA\Property(property: 'email',    type: 'string', format: 'email'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'roleId',   type: 'integer'),
            new OA\Property(property: 'clientId', type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Usuario creado')]
)]
#[OA\Put(path: '/users/{id}', operationId: 'usersUpdate', tags: ['Users'], summary: 'Actualizar usuario',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'fullName', type: 'string')]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Usuario actualizado'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Delete(path: '/users/{id}', operationId: 'usersDestroy', tags: ['Users'], summary: 'Eliminar usuario',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Eliminado'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Get(path: '/users/assignment/leaders', operationId: 'assignmentLeaders', tags: ['UserAssignments'], summary: 'Listar lideres disponibles',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de lideres')]
)]
#[OA\Get(path: '/users/assignment/assignable-users', operationId: 'assignableUsers', tags: ['UserAssignments'], summary: 'Usuarios asignables',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de usuarios asignables')]
)]
#[OA\Get(path: '/users/{leaderUserId}/assignments', operationId: 'assignmentsIndex', tags: ['UserAssignments'], summary: 'Asignaciones de un lider',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'leaderUserId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Asignaciones del lider')]
)]
#[OA\Put(path: '/users/assignments', operationId: 'assignmentsUpsert', tags: ['UserAssignments'], summary: 'Crear o actualizar asignaciones (upsert)',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'leaderUserId',    type: 'integer'),
            new OA\Property(property: 'assignedUserIds', type: 'array', items: new OA\Items(type: 'integer')),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Asignaciones guardadas')]
)]
#[OA\Delete(path: '/users/{leaderUserId}/assignments/{assignedUserId}', operationId: 'assignmentsDestroy', tags: ['UserAssignments'], summary: 'Eliminar asignacion',
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
