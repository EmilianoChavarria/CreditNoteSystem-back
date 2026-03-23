# API de Permisos (roles/modules/actions)

Este documento resume los endpoints que se ajustaron para el nuevo esquema relacional:

- `actions`
- `modules` (con jerarquia por `parentid`)
- `permissions` (matriz por `roleid + moduleid + actionid`)

## Base

- Prefijo API: `api/`
- Middleware: `jwt`
- Archivo de rutas: `routes/api/rolesPermission.php`
- Archivo de rutas por tipo de solicitud: `routes/api/requestTypePermissions.php`
- Archivo de rutas de actions: `routes/api/actions.php`
- Archivo de rutas de modulos: `routes/api/modules.php`

## Envoltorio de respuesta

Todos los endpoints responden con esta estructura base:

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "OK",
  "data": {},
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 1) Crear modulo

`POST /api/modules`

Crea un modulo para sidebar (padre o hijo) con soporte de accion requerida.

### Request JSON

```json
{
  "name": "New Request",
  "parentid": 10,
  "url": "/requests/new",
  "icon": "pi pi-plus",
  "orderindex": 2,
  "requiredactionid": 2
}
```

Tambien puedes enviar `moduleName` en lugar de `name` (compatibilidad con version anterior).

### Validaciones

- `name` opcional, string, max 100
- `moduleName` opcional, string, max 150
- Debes enviar al menos uno: `name` o `moduleName`
- `parentid` opcional, entero, existe en `modules.id`
- `url` opcional, string, max 255
- `icon` opcional, string, max 50
- `orderindex` opcional, entero, minimo 0
- `requiredactionid` opcional, entero, existe en `actions.id`

### Response JSON (ejemplo)

```json
{
  "codeStatus": 201,
  "success": true,
  "message": "Module creado",
  "data": {
    "id": 11,
    "name": "New Request",
    "moduleName": "New Request",
    "parentid": 10,
    "url": "/requests/new",
    "icon": "pi pi-plus",
    "orderindex": 2,
    "requiredactionid": 2
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 2) Crear action

`POST /api/actions`

Crea una accion de permisos (por ejemplo: Ver Listado, Crear, Carga Masiva).

### Request JSON

```json
{
  "name": "Carga Masiva",
  "slug": "bulk_load"
}
```

### Validaciones

- `name` requerido, string, max 50, unico en `actions.name`
- `slug` requerido, string, max 50, formato `alpha_dash`, unico en `actions.slug`

### Response JSON (ejemplo)

```json
{
  "codeStatus": 201,
  "success": true,
  "message": "Action creada",
  "data": {
    "id": 3,
    "name": "Carga Masiva",
    "slug": "bulk_load"
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 3) Asignar/actualizar permisos (upsert)

`POST /api/rolesPermission/assign`

Permite crear o actualizar permisos por combinacion:

- `roleid`
- `moduleid`
- `actionid`

### Request JSON

```json
{
  "permissions": [
    {
      "roleid": 1,
      "moduleid": 10,
      "actionid": 1,
      "isallowed": true
    },
    {
      "roleid": 1,
      "moduleid": 11,
      "actionid": 2,
      "isallowed": false
    }
  ]
}
```

### Validaciones

- `permissions` requerido, array, minimo 1
- `permissions.*.roleid` requerido, entero, existe en `roles.id`
- `permissions.*.moduleid` requerido, entero, existe en `modules.id`
- `permissions.*.actionid` requerido, entero, existe en `actions.id`
- `permissions.*.isallowed` requerido, booleano

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Permisos actualizados correctamente",
  "data": {
    "summary": {
      "total": 2,
      "created": 1,
      "updated": 1
    },
    "permissions": [
      {
        "id": 1,
        "roleid": 1,
        "moduleid": 10,
        "actionid": 1,
        "isallowed": true,
        "role": {
          "id": 1,
          "roleName": "Admin"
        },
        "module": {
          "id": 10,
          "name": "Requests"
        },
        "action": {
          "id": 1,
          "name": "Ver Listado",
          "slug": "view_list"
        }
      }
    ]
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 4) Listar todos los permisos

`GET /api/rolesPermission`

Retorna todos los registros de `permissions` con relaciones:

- `role`
- `module`
- `action`

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Permisos",
  "data": [
    {
      "id": 1,
      "roleid": 1,
      "moduleid": 10,
      "actionid": 1,
      "isallowed": true,
      "role": { "id": 1, "roleName": "Admin" },
      "module": { "id": 10, "name": "Requests" },
      "action": { "id": 1, "name": "Ver Listado", "slug": "view_list" }
    }
  ],
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 5) Listar permisos por rol

`GET /api/rolesPermission/role/{roleId}`

Ejemplo: `GET /api/rolesPermission/role/1`

Retorna la matriz de permisos de un rol especifico.

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Permisos por rol",
  "data": [
    {
      "id": 1,
      "roleid": 1,
      "moduleid": 10,
      "actionid": 1,
      "isallowed": true,
      "role": { "id": 1, "roleName": "Admin" },
      "module": { "id": 10, "name": "Requests" },
      "action": { "id": 1, "name": "Ver Listado", "slug": "view_list" }
    }
  ],
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 6) Sidebar por rol (jerarquico y filtrado)

`GET /api/rolesPermission/sidebar/{roleId}`

Ejemplo: `GET /api/rolesPermission/sidebar/1`

Tambien puedes consumir el sidebar del usuario autenticado sin enviar roleId:

`GET /api/rolesPermission/sidebar`

En este caso, el backend toma `roleId` desde el JWT (`authUser.roleId`).

Retorna solo modulos visibles para el rol, respetando:

- Jerarquia por `parentid`
- `requiredactionid` del modulo
- Acciones permitidas (`isallowed = true`)

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Sidebar por rol",
  "data": [
    {
      "id": 10,
      "name": "Requests",
      "url": "/requests",
      "icon": "pi pi-file",
      "orderIndex": 1,
      "requiredAction": null,
      "allowedActions": [
        {
          "id": 1,
          "name": "Ver Listado",
          "slug": "view_list"
        }
      ],
      "children": [
        {
          "id": 11,
          "name": "New Request",
          "url": "/requests/new",
          "icon": "pi pi-plus",
          "orderIndex": 2,
          "requiredAction": {
            "id": 2,
            "name": "Crear",
            "slug": "create"
          },
          "allowedActions": [],
          "children": []
        }
      ]
    }
  ],
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

### Response JSON para usuario autenticado (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Sidebar del usuario autenticado",
  "data": {
    "roleid": 16,
    "sidebar": [
      {
        "id": 1,
        "name": "SIDEBAR.HOME",
        "url": "/app/dashboard",
        "icon": "layout-dashboard",
        "orderIndex": 1,
        "requiredAction": null,
        "allowedActions": [
          {
            "id": 4,
            "name": "View",
            "slug": "view"
          }
        ],
        "children": []
      }
    ]
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 7) Validar acceso puntual

`POST /api/rolesPermission/check`

Valida si un rol tiene permiso para una accion en un modulo.

### Request JSON

```json
{
  "roleid": 1,
  "moduleid": 11,
  "action": "create"
}
```

`action` puede enviarse como:

- `slug` (string): `"create"`
- `id` (number): `2`

`roleid` es opcional: si no se envia, se toma del usuario autenticado por JWT.

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Validación de permiso",
  "data": {
    "roleid": 1,
    "moduleid": 11,
    "action": "create",
    "isallowed": false
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 8) Asignar/actualizar permisos por tipo de solicitud (upsert)

`POST /api/requestTypePermissions/assign`

Permite crear o actualizar permisos por combinacion:

- `role_id`
- `request_type_id`
- `action_id`

### Request JSON

```json
{
  "permissions": [
    {
      "role_id": 16,
      "request_type_id": 2,
      "action_id": 1,
      "is_allowed": true
    }
  ]
}
```

### Validaciones

- `permissions` requerido, array, minimo 1
- `permissions.*.role_id` requerido, entero, existe en `roles.id`
- `permissions.*.request_type_id` requerido, entero, existe en `requesttype.id`
- `permissions.*.action_id` requerido, entero, existe en `actions.id`
- `permissions.*.is_allowed` requerido, booleano

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Permisos por tipo de solicitud actualizados correctamente",
  "data": {
    "summary": {
      "total": 1,
      "created": 1,
      "updated": 0
    },
    "permissions": [
      {
        "id": 1,
        "role_id": 16,
        "request_type_id": 2,
        "action_id": 1,
        "is_allowed": true
      }
    ]
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## 9) Listar permisos por tipo de solicitud

`GET /api/requestTypePermissions`

Retorna todos los registros de `requesttypepermissions` con relaciones:

- `role`
- `requestType`
- `action`

### Endpoints adicionales

- `GET /api/requestTypePermissions/role/{roleId}`
- `GET /api/requestTypePermissions/request-type/{requestTypeId}`

---

## 10) Validar acceso puntual por tipo de solicitud

`POST /api/requestTypePermissions/check`

### Request JSON

```json
{
  "role_id": 16,
  "request_type_id": 2,
  "action": "view"
}
```

`action` puede enviarse como:

- `slug` (string): `"view"`
- `id` (number): `1`

`role_id` es opcional: si no se envia, se toma del usuario autenticado por JWT.

### Response JSON (ejemplo)

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Validación de permiso por tipo de solicitud",
  "data": {
    "role_id": 16,
    "request_type_id": 2,
    "action": "view",
    "is_allowed": true
  },
  "errors": null,
  "timestamp": "2026-03-23T12:34:56+00:00"
}
```

---

## Cambios de contrato vs version anterior

Antes (`rolespermission` legado):

- Se usaba `requestTypeId`, `roleId`, `hasAccess`

Ahora (`permissions` nuevo):

- Se usa `roleid`, `moduleid`, `actionid`, `isallowed`
- Se agregan endpoints para:
  - permisos por rol
  - sidebar filtrado por permisos
  - validacion puntual de acceso

---

## Ejemplo rapido para Postman

### Headers

- `Authorization: Bearer <token_jwt>`
- `Content-Type: application/json`

### Flujo recomendado

1. Crear modulos con `POST /api/modules`
2. Crear acciones con `POST /api/actions`
3. Ejecutar `POST /api/rolesPermission/assign`
4. Consultar `GET /api/rolesPermission/role/{roleId}`
5. Renderizar sidebar con `GET /api/rolesPermission/sidebar/{roleId}`
6. Validar acciones de botones con `POST /api/rolesPermission/check`
7. Asignar permisos por tipo con `POST /api/requestTypePermissions/assign`
8. Validar acceso por tipo con `POST /api/requestTypePermissions/check`
