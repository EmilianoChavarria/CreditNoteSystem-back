<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// ── Roles ─────────────────────────────────────────────────────────────────────
#[OA\Get(path: '/roles', operationId: 'rolesList', tags: ['Roles'], summary: 'Listar todos los roles',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de roles', content: new OA\JsonContent(
        properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Role'))]
    ))]
)]
#[OA\Post(path: '/roles', operationId: 'rolesStore', tags: ['Roles'], summary: 'Crear un rol',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleName'],
        properties: [
            new OA\Property(property: 'roleName', type: 'string', example: 'SUPERVISOR'),
            new OA\Property(property: 'color',    type: 'string', nullable: true, example: '#FF5733'),
        ]
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
        properties: [
            new OA\Property(property: 'roleName', type: 'string'),
            new OA\Property(property: 'color',    type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Rol actualizado')]
)]
#[OA\Patch(path: '/roles/{id}', operationId: 'rolesPatch', tags: ['Roles'], summary: 'Actualizar rol parcialmente',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'roleName', type: 'string'),
            new OA\Property(property: 'color',    type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Rol actualizado')]
)]
#[OA\Get(path: '/roles/{id}/equivalent-role', operationId: 'rolesEquivalentRole', tags: ['Roles'], summary: 'Obtener rol equivalente configurado',
    description: 'El rol equivalente se usa como fallback cuando no hay usuario disponible con el rol principal en el siguiente paso del workflow.',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Rol equivalente')]
)]
#[OA\Patch(path: '/roles/{id}/equivalent-role', operationId: 'rolesSetEquivalentRole', tags: ['Roles'], summary: 'Configurar rol equivalente (fallback de asignacion)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [new OA\Property(property: 'equivalentRoleId', type: 'integer', nullable: true, example: 4)]
    )),
    responses: [new OA\Response(response: 200, description: 'Rol equivalente actualizado')]
)]
#[OA\Delete(path: '/roles/{id}', operationId: 'rolesDestroy', tags: ['Roles'], summary: 'Eliminar rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Rol eliminado')]
)]
// ── Modules ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/modules', operationId: 'modulesList', tags: ['Modules'], summary: 'Listar todos los modulos (con hijos anidados)',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista jerarquica de modulos')]
)]
#[OA\Post(path: '/modules', operationId: 'modulesStore', tags: ['Modules'], summary: 'Crear modulo de sidebar',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'slug'],
        properties: [
            new OA\Property(property: 'name',     type: 'string',  example: 'Solicitudes'),
            new OA\Property(property: 'slug',     type: 'string',  example: 'requests'),
            new OA\Property(property: 'icon',     type: 'string',  nullable: true, example: 'FileText'),
            new OA\Property(property: 'order',    type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'parentId', type: 'integer', nullable: true, description: 'ID del modulo padre para crear submenu'),
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
        properties: [
            new OA\Property(property: 'name',  type: 'string'),
            new OA\Property(property: 'slug',  type: 'string'),
            new OA\Property(property: 'icon',  type: 'string', nullable: true),
            new OA\Property(property: 'order', type: 'integer', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Modulo actualizado')]
)]
#[OA\Delete(path: '/modules/{id}', operationId: 'modulesDestroy', tags: ['Modules'], summary: 'Eliminar modulo',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Modulo eliminado')]
)]
// ── Actions ───────────────────────────────────────────────────────────────────
#[OA\Get(path: '/actions', operationId: 'actionsList', tags: ['Actions'], summary: 'Listar todas las acciones de permiso',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Lista de acciones')]
)]
#[OA\Post(path: '/actions', operationId: 'actionsStore', tags: ['Actions'], summary: 'Crear accion de permiso',
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
#[OA\Get(path: '/rolesPermission', operationId: 'rolesPermissionList', tags: ['RolesPermission'], summary: 'Listar todos los permisos de modulo',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Matriz de permisos rol-modulo-accion')]
)]
#[OA\Post(path: '/rolesPermission/assign', operationId: 'rolesPermissionAssign', tags: ['RolesPermission'],
    summary: 'Asignar o actualizar permisos de modulo a un rol (upsert)',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'moduleId', 'permissions'],
        properties: [
            new OA\Property(property: 'roleId',      type: 'integer', example: 2),
            new OA\Property(property: 'moduleId',    type: 'integer', example: 5),
            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['view', 'create', 'approve']),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Permisos guardados')]
)]
#[OA\Get(path: '/rolesPermission/role/{roleId}', operationId: 'rolesPermissionByRole', tags: ['RolesPermission'], summary: 'Permisos de modulo de un rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Permisos del rol')]
)]
#[OA\Get(path: '/rolesPermission/sidebar', operationId: 'rolesPermissionSidebar', tags: ['RolesPermission'],
    summary: 'Sidebar filtrado para el usuario autenticado',
    description: 'Retorna solo los modulos a los que el usuario tiene acceso segun su rol.',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Modulos del sidebar del usuario')]
)]
#[OA\Get(path: '/rolesPermission/sidebar/{roleId}', operationId: 'rolesPermissionSidebarByRole', tags: ['RolesPermission'], summary: 'Sidebar para un rol especifico',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Modulos visibles del rol')]
)]
#[OA\Post(path: '/rolesPermission/check', operationId: 'rolesPermissionCheck', tags: ['RolesPermission'], summary: 'Verificar si un rol tiene acceso a un modulo',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'moduleSlug'],
        properties: [
            new OA\Property(property: 'roleId',     type: 'integer', example: 2),
            new OA\Property(property: 'moduleSlug', type: 'string',  example: 'requests'),
            new OA\Property(property: 'actionSlug', type: 'string',  nullable: true, example: 'approve'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado: allowed true/false')]
)]
// ── Classifications ───────────────────────────────────────────────────────────
#[OA\Post(path: '/classifications', operationId: 'classificationsStore', tags: ['Classifications'], summary: 'Crear clasificacion de solicitud',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'requestTypeId'],
        properties: [
            new OA\Property(property: 'name',          type: 'string',  example: 'Devolucion por defecto de fabrica'),
            new OA\Property(property: 'requestTypeId', type: 'integer', example: 1),
            new OA\Property(property: 'type',          type: 'string',  nullable: true, example: 'ventas'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Clasificacion creada')]
)]
#[OA\Get(path: '/classifications/grouped', operationId: 'classificationsGrouped', tags: ['Classifications'], summary: 'Clasificaciones agrupadas por tipo de solicitud',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Clasificaciones agrupadas')]
)]
#[OA\Get(path: '/classifications/requestType/{typeRequestId}', operationId: 'classificationsByType', tags: ['Classifications'], summary: 'Clasificaciones de un tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'typeRequestId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Clasificaciones del tipo')]
)]
#[OA\Get(path: '/classifications/my-pending', operationId: 'classificationsMyPending', tags: ['Classifications'],
    summary: 'Clasificaciones usadas en solicitudes pendientes del usuario actual',
    description: 'Retorna solo las clasificaciones que aparecen en solicitudes cuyo paso actual esta asignado al usuario. Util para filtros en pantalla de pendientes.',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Clasificaciones relevantes al usuario')]
)]
#[OA\Post(path: '/classifications/{id}/reasons', operationId: 'classificationsReasons', tags: ['Classifications'],
    summary: 'Sincronizar razones de rechazo a una clasificacion (reemplaza las existentes)',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['reasonIds'],
        properties: [
            new OA\Property(property: 'reasonIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3, 5]),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Razones sincronizadas')]
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
        properties: [
            new OA\Property(property: 'name',   type: 'string', example: 'Nota de Credito'),
            new OA\Property(property: 'prefix', type: 'string', nullable: true, example: 'NC', description: 'Prefijo para numeracion de solicitudes'),
        ]
    )),
    responses: [new OA\Response(response: 201, description: 'Tipo creado')]
)]
#[OA\Put(path: '/requestType/{id}', operationId: 'requestTypeUpdate', tags: ['RequestTypes'], summary: 'Actualizar tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'name',   type: 'string'),
            new OA\Property(property: 'prefix', type: 'string', nullable: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Tipo actualizado')]
)]
#[OA\Delete(path: '/requestType/{id}', operationId: 'requestTypeDestroy', tags: ['RequestTypes'], summary: 'Eliminar tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Tipo eliminado')]
)]
// ── Request Type Permissions ──────────────────────────────────────────────────
#[OA\Get(path: '/requestTypePermissions', operationId: 'rtpList', tags: ['RequestTypePermissions'], summary: 'Listar permisos por tipo de solicitud',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Matriz de permisos tipo-rol-accion')]
)]
#[OA\Post(path: '/requestTypePermissions/assign', operationId: 'rtpAssign', tags: ['RequestTypePermissions'],
    summary: 'Asignar permiso de accion a un rol para un tipo de solicitud',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'requestTypeId', 'actionId', 'isAllowed'],
        properties: [
            new OA\Property(property: 'roleId',        type: 'integer', example: 2),
            new OA\Property(property: 'requestTypeId', type: 'integer', example: 1),
            new OA\Property(property: 'actionId',      type: 'integer', example: 3),
            new OA\Property(property: 'isAllowed',     type: 'boolean', example: true),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Permiso guardado')]
)]
#[OA\Get(path: '/requestTypePermissions/role/{roleId}', operationId: 'rtpByRole', tags: ['RequestTypePermissions'], summary: 'Permisos de tipo de solicitud de un rol',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Permisos del rol')]
)]
#[OA\Get(path: '/requestTypePermissions/request-type/{requestTypeId}', operationId: 'rtpByRequestType', tags: ['RequestTypePermissions'],
    summary: 'Permisos de todos los roles para un tipo de solicitud',
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'requestTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'Permisos por tipo')]
)]
#[OA\Post(path: '/requestTypePermissions/check', operationId: 'rtpCheck', tags: ['RequestTypePermissions'],
    summary: 'Verificar si un rol puede ejecutar una accion en un tipo de solicitud',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['roleId', 'requestTypeId', 'actionSlug'],
        properties: [
            new OA\Property(property: 'roleId',        type: 'integer', example: 2),
            new OA\Property(property: 'requestTypeId', type: 'integer', example: 1),
            new OA\Property(property: 'actionSlug',    type: 'string',  example: 'create'),
        ]
    )),
    responses: [new OA\Response(response: 200, description: 'Resultado: allowed true/false')]
)]
class CatalogDocs
{
}
