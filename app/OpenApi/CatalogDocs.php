<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Roles ─────────────────────────────────────────────────────────────────────
#[OA\Get(path: '/roles', operationId: 'rolesList', tags: ['Roles'], summary: 'Listar todos los roles',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de roles')]
)]
#[OA\Post(path: '/roles', operationId: 'rolesStore', tags: ['Roles'], summary: 'Crear un rol',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleName'],
        properties: [new OA\Property(property: 'roleName', type: 'string', example: 'SUPERVISOR')]
    )),
    responses: [new OA\Response(response: 201, description: 'Rol creado')]
)]
#[OA\Get(path: '/roles/{id}', operationId: 'rolesShow', tags: ['Roles'], summary: 'Obtener rol por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del rol'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Put(path: '/roles/{id}', operationId: 'rolesUpdate', tags: ['Roles'], summary: 'Actualizar rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'roleName', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Rol actualizado')]
)]
#[OA\Delete(path: '/roles/{id}', operationId: 'rolesDestroy', tags: ['Roles'], summary: 'Eliminar rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Rol eliminado')]
)]
// ── Modules ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/modules', operationId: 'modulesList', tags: ['Modules'], summary: 'Listar todos los modulos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de modulos')]
)]
#[OA\Post(path: '/modules', operationId: 'modulesStore', tags: ['Modules'], summary: 'Crear modulo',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'slug'],
        properties: [
            new OA\Property(property: 'name',  type: 'string'),
            new OA\Property(property: 'slug',  type: 'string'),
            new OA\Property(property: 'icon',  type: 'string',  nullable: true),
            new OA\Property(property: 'order', type: 'integer', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Modulo creado')]
)]
#[OA\Get(path: '/modules/{id}', operationId: 'modulesShow', tags: ['Modules'], summary: 'Obtener modulo por ID',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Datos del modulo'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ]
)]
#[OA\Put(path: '/modules/{id}', operationId: 'modulesUpdate', tags: ['Modules'], summary: 'Actualizar modulo',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'name', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Modulo actualizado')]
)]
#[OA\Delete(path: '/modules/{id}', operationId: 'modulesDestroy', tags: ['Modules'], summary: 'Eliminar modulo',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Modulo eliminado')]
)]
// ── Actions ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/actions', operationId: 'actionsList', tags: ['Actions'], summary: 'Listar todas las acciones',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de acciones')]
)]
#[OA\Post(path: '/actions', operationId: 'actionsStore', tags: ['Actions'], summary: 'Crear una accion',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'slug'],
        properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Aprobar'),
            new OA\Property(property: 'slug', type: 'string', example: 'approve'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Accion creada')]
)]
// ── Roles Permission ──────────────────────────────────────────────────────────
#[OA\Get(path: '/rolesPermission', operationId: 'rolesPermissionList', tags: ['RolesPermission'], summary: 'Listar permisos de modulos',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de permisos')]
)]
#[OA\Post(path: '/rolesPermission/assign', operationId: 'rolesPermissionAssign', tags: ['RolesPermission'], summary: 'Asignar permisos de modulo a rol',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'moduleId', 'permissions'],
        properties: [
            new OA\Property(property: 'roleId',      type: 'integer'),
            new OA\Property(property: 'moduleId',    type: 'integer'),
            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Permisos asignados')]
)]
#[OA\Get(path: '/rolesPermission/role/{roleId}', operationId: 'rolesPermissionByRole', tags: ['RolesPermission'], summary: 'Permisos de un rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Permisos del rol')]
)]
#[OA\Get(path: '/rolesPermission/sidebar', operationId: 'rolesPermissionSidebar', tags: ['RolesPermission'], summary: 'Sidebar del usuario autenticado',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Modulos visibles')]
)]
#[OA\Get(path: '/rolesPermission/sidebar/{roleId}', operationId: 'rolesPermissionSidebarByRole', tags: ['RolesPermission'], summary: 'Sidebar por rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Modulos visibles del rol')]
)]
#[OA\Post(path: '/rolesPermission/check', operationId: 'rolesPermissionCheck', tags: ['RolesPermission'], summary: 'Verificar acceso de rol a modulo',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'moduleSlug'],
        properties: [
            new OA\Property(property: 'roleId',     type: 'integer'),
            new OA\Property(property: 'moduleSlug', type: 'string'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de verificacion')]
)]
// ── Classifications ───────────────────────────────────────────────────────────
#[OA\Post(path: '/classifications', operationId: 'classificationsStore', tags: ['Classifications'], summary: 'Crear clasificacion',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'requestTypeId'],
        properties: [
            new OA\Property(property: 'name',          type: 'string'),
            new OA\Property(property: 'requestTypeId', type: 'integer'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Clasificacion creada')]
)]
#[OA\Get(path: '/classifications/grouped', operationId: 'classificationsGrouped', tags: ['Classifications'], summary: 'Clasificaciones agrupadas por tipo',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Clasificaciones agrupadas')]
)]
#[OA\Get(path: '/classifications/requestType/{typeRequestId}', operationId: 'classificationsByType', tags: ['Classifications'], summary: 'Clasificaciones por tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'typeRequestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Clasificaciones del tipo')]
)]
// ── Request Types ─────────────────────────────────────────────────────────────
#[OA\Get(path: '/requestType', operationId: 'requestTypeList', tags: ['RequestTypes'], summary: 'Listar tipos de solicitud',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Tipos de solicitud')]
)]
#[OA\Post(path: '/requestType', operationId: 'requestTypeStore', tags: ['RequestTypes'], summary: 'Crear tipo de solicitud',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name'],
        properties: [new OA\Property(property: 'name', type: 'string')]
    )),
    responses: [new OA\Response(response: 201, description: 'Tipo creado')]
)]
#[OA\Put(path: '/requestType/{id}', operationId: 'requestTypeUpdate', tags: ['RequestTypes'], summary: 'Actualizar tipo',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'name', type: 'string')]
    )),
    responses: [new OA\Response(response: 200, description: 'Tipo actualizado')]
)]
#[OA\Delete(path: '/requestType/{id}', operationId: 'requestTypeDestroy', tags: ['RequestTypes'], summary: 'Eliminar tipo',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Tipo eliminado')]
)]
// ── Request Type Permissions ──────────────────────────────────────────────────
#[OA\Get(path: '/requestTypePermissions', operationId: 'rtpList', tags: ['RequestTypePermissions'], summary: 'Listar permisos por tipo',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Permisos')]
)]
#[OA\Post(path: '/requestTypePermissions/assign', operationId: 'rtpAssign', tags: ['RequestTypePermissions'], summary: 'Asignar permiso de accion a rol',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'requestTypeId', 'actionId', 'isAllowed'],
        properties: [
            new OA\Property(property: 'roleId',        type: 'integer'),
            new OA\Property(property: 'requestTypeId', type: 'integer'),
            new OA\Property(property: 'actionId',      type: 'integer'),
            new OA\Property(property: 'isAllowed',     type: 'boolean'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Permiso asignado')]
)]
#[OA\Get(path: '/requestTypePermissions/role/{roleId}', operationId: 'rtpByRole', tags: ['RequestTypePermissions'], summary: 'Permisos de un rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Permisos del rol')]
)]
#[OA\Get(path: '/requestTypePermissions/request-type/{requestTypeId}', operationId: 'rtpByRequestType', tags: ['RequestTypePermissions'], summary: 'Permisos por tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Permisos del tipo')]
)]
#[OA\Post(path: '/requestTypePermissions/check', operationId: 'rtpCheck', tags: ['RequestTypePermissions'], summary: 'Verificar permiso de accion',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'requestTypeId', 'actionSlug'],
        properties: [
            new OA\Property(property: 'roleId',        type: 'integer'),
            new OA\Property(property: 'requestTypeId', type: 'integer'),
            new OA\Property(property: 'actionSlug',    type: 'string'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado de verificacion')]
)]
class CatalogDocs
{
}
