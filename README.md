# Notas de Crédito — Backend API

Backend en **Laravel 12** para la gestión de solicitudes de crédito, débito, auditoría, re-facturación y devolución. Incluye motor de flujos de aprobación configurable, carga masiva asíncrona, permisos por rol, integración con FESA (sistema de facturación externo) y soporte de WebSockets (Reverb/Pusher).

---

## Stack técnico

| Capa | Tecnología |
|---|---|
| Framework | Laravel 12, PHP 8.2+ |
| Auth | JWT (`firebase/php-jwt`) |
| Colas | Driver `database` |
| WebSockets | Laravel Reverb + Pusher PHP Server |
| PDF | DomPDF 3.0 |
| Email | SendGrid (`symfony/sendgrid-mailer`) |
| Facturación | FESA Web Service (NuSOAP) + XML parsing |
| Tipo de cambio | Banxico API |
| API Docs | L5-Swagger 11 |

---

## Requisitos

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL (recomendado)
- Worker de colas activo para batches

---

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

---

## Variables de entorno importantes

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=notas_credito
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

# JWT
JWT_TTL_MINUTES=120
JWT_ISSUER=backend-notasCredito
JWT_SECRET=tu_secreto_jwt

# Seguridad
SECURITY_MAX_USER_ATTEMPTS=5
SECURITY_MAX_IP_ATTEMPTS=10
SECURITY_USER_LOCK_MINUTES=1440

# Carga masiva
BULK_USERS_DEFAULT_PASSWORD=ChangeMe123!

# FESA (facturación externa)
FESA_WS_URL=
FESA_WS_USER=
FESA_WS_PASSWORD=

# Pusher / Reverb
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=

# SendGrid
SENDGRID_API_KEY=
MAIL_FROM_ADDRESS=
```

---

## Ejecución en desarrollo

### Todo en un comando

```bash
composer run dev
```

Levanta servidor, worker de cola, logs y Vite en paralelo.

### Manual

```bash
# Terminal 1 — API
php artisan serve

# Terminal 2 — Worker (requerido para batches)
php artisan queue:work database --queue=default --tries=1

# Terminal 3 — Assets (opcional)
npm run dev
```

---

## Comandos útiles

```bash
php artisan route:list
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan test
```

---

## Estructura del proyecto

```
app/
├── Actions/
│   ├── Requests/          # Operaciones de alto nivel: Approve, Reject, Cancel, SendBack (single y mass)
│   ├── ReturnOrders/      # CreateReturnOrderAction, LinkReturnOrderToRequestAction
│   └── Users/             # Login, Register, Create, Update, ChangePassword
├── Http/
│   ├── Controllers/Api/   # Un controlador por dominio (ver sección Controladores)
│   ├── Middleware/
│   │   └── JwtAuth.php    # Valida token JWT, extrae usuario, verifica permisos
│   ├── Requests/          # Form Requests por módulo (validación de entrada)
│   └── Resources/         # API Resources: transforman modelos a JSON (ver sección Resources)
├── Jobs/
│   ├── ProcessBatchJob.php      # Despacha ProcessBatchItemJob por cada ítem
│   └── ProcessBatchItemJob.php  # Procesa un ítem individual de un batch
├── Models/                # Entidades Eloquent (ver sección Modelos)
├── Services/              # Toda la lógica de negocio (ver sección Servicios)
└── Support/
    └── ApiResponse.php    # Formato uniforme de respuesta {ok, message, data, errors}

config/
├── bulk_upload.php        # Handlers por tipo de batch, campo matrix, password default
└── security.php           # JWT TTL, límites de intentos, reglas de bloqueo

database/
└── migrations/            # Una migración por cambio de esquema

routes/
├── api.php                # Incluye todos los archivos de routes/api/
└── api/                   # 27 archivos segmentados por dominio
```

---

## Patrón de un módulo

Todos los módulos siguen la misma estructura. Ejemplo: módulo **ReturnOrders**.

```
# 1. Ruta
routes/api/returnOrders.php
  → POST /return-orders  →  ReturnOrderController@store

# 2. Controlador
app/Http/Controllers/Api/ReturnOrderController.php
  → Valida con CreateReturnOrderInput
  → Delega a CreateReturnOrderAction

# 3. Form Request (validación)
app/Http/Requests/ReturnOrders/CreateReturnOrderInput.php
  → rules(): clientId required, currency in:MXN|USD, chargeTypeId exists, items array

# 4. Action (orquesta el caso de uso)
app/Actions/ReturnOrders/CreateReturnOrderAction.php
  → Llama a ReturnOrderService::createReturnOrder(...)

# 5. Service (lógica de negocio)
app/Services/ReturnOrderService.php
  → Valida cantidades disponibles
  → DB::transaction → ReturnOrder::create + ReturnOrderItem::create + ReturnOrderItemHistory::create

# 6. Model
app/Models/ReturnOrder.php
  → $table = 'returnorders'
  → $fillable, $casts, relationships

# 7. Resource (respuesta)
app/Http/Resources/ReturnOrderResource.php
  → Transforma el modelo a JSON
```

Regla: **el controlador no tiene lógica de negocio**. Solo recibe, valida, delega y responde.

---

## Formato de respuesta API

Toda la API usa `App\Support\ApiResponse`:

```json
// Éxito
{ "ok": true,  "message": "Solicitud aprobada", "data": { ... } }

// Error
{ "ok": false, "message": "No tienes permisos", "data": null }
```

El código HTTP siempre acompaña la respuesta (200, 201, 422, 403, 404).

---

## Autenticación (JWT)

| Endpoint | Descripción |
|---|---|
| `POST /api/auth/login` | Login; retorna `token` |
| `GET /api/auth/verify` | Verifica y refresca token |
| `POST /api/auth/logout` | Invalida sesión en DB |
| `POST /api/auth/change-password` | Cambio de contraseña autenticado |

Header requerido en todas las rutas protegidas:

```
Authorization: Bearer <token>
```

El middleware `JwtAuth` valida firma, expiración y que el token no haya sido invalidado (`UserSecurity.sessionToken`).

---

## Sistema de flujos de aprobación (Workflow Engine)

Este es el núcleo del sistema. Una solicitud pasa por N pasos de aprobación definidos en base de datos.

### Modelos involucrados

| Modelo | Tabla | Propósito |
|---|---|---|
| `Workflow` | workflows | Agrupa pasos; asociado a un `RequestType` |
| `WorkflowStep` | workflowsteps | Un paso del flujo: quién aprueba, si es inicial/final |
| `WorkflowStepTransition` | workflowsteptransitions | Transiciones condicionales entre pasos (campo + operador + valor) |
| `WorkflowRequestCurrentStep` | workflowrequestcurrentsteps | Paso actual de cada solicitud en tiempo real |
| `WorkflowRequestStep` | workflowrequeststeps | Historial de pasos por solicitud |
| `WorkflowRequestHistory` | workflowrequesthistory | Registro de cada acción (approved/rejected/sent_back) |

### Ciclo de vida de una solicitud

```
[Creación]
  RequestCrudService::createRequest()
    → Guarda Request con status = 'pending'
    → Llama RequestWorkflowService::assignRequestToWorkflow()
      → Busca Workflow activo para RequestType + clasificación
      → Localiza el primer paso operacional
      → Crea WorkflowRequestCurrentStep (paso actual)
      → Notifica al usuario/rol asignado

[Aprobación]  POST /api/requests/{id}/approve
  RequestWorkflowService::approve()
    → Verifica permisos (ver abajo)
    → Resuelve siguiente paso (condicional o lineal)
    → Actualiza WorkflowRequestStep a 'approved'
    → Crea WorkflowRequestHistory
    → Si es paso final → Request.status = 'approved'
    → Si no → crea nuevo WorkflowRequestStep y actualiza CurrentStep
    → Notifica siguiente usuario/rol

[Rechazo]  POST /api/requests/{id}/reject
  → Igual que aprobación pero status = 'rejected'
  → Request.status = 'rejected'

[Regreso]  POST /api/requests/{id}/send-back
  → Retrocede al paso anterior o al paso indicado
  → Re-crea WorkflowRequestStep en ese paso

[Cancelación]  POST /api/requests/{id}/cancel
  → Request.status = 'cancelled' (acción irreversible)
```

### Verificación de permisos por paso

```php
// RequestWorkflowService, ~línea 284-291
$isAdmin        = /* rol administrador */;
$isAssignedUser = $currentStep->assignedUserId === $authUser->id;
$isRoleAssigned = $currentStep->assignedRoleId === $authUser->roleId
                  && Role::whereIn('roleName', ['REPLENISHMENT', 'WAREHOUSE', 'IT'])->exists();

if (!$isAdmin && !$isAssignedUser && !$isRoleAssigned) → 403
```

**Roles "broadcast"** (REPLENISHMENT, WAREHOUSE, IT): cualquier usuario del rol puede aprobar/rechazar/ver pendientes. No se asigna a un usuario específico, sino al rol completo.

### Resolución del usuario para el siguiente paso

| Rol configurado en el paso | Usuario asignado |
|---|---|
| `REQUESTER` | El creador de la solicitud |
| `CS LEADER` | El líder asignado al creador (`UserAssignment`) |
| `MANAGER` | El gerente del cliente (ventas/finanzas/marketing según clasificación) |
| `REPLENISHMENT`, `WAREHOUSE`, `IT` | Sin usuario específico (broadcast al rol) |
| Cualquier otro rol | Primer usuario activo con ese rol (o rol equivalente) |

### Transiciones condicionales

Un paso puede tener múltiples transiciones con condiciones. El sistema evalúa por prioridad:

```
WorkflowStepTransition:
  field:    'totalAmount'
  operator: '>'
  value:    '150000'
  → nextStepId: paso_vp_controller

  (sin condición, prioridad menor)
  → nextStepId: paso_finanzas
```

Operadores soportados: `>`, `<`, `>=`, `<=`, `==`, `!=`

Campo especial: `totalAmount` en solicitudes DM usa `warehouseTotal`. Si la moneda de la solicitud es MXN y el umbral se compara contra USD, el servicio consulta Banxico para la conversión.

---

## Módulo de Devoluciones (Return Orders)

Gestiona devoluciones de producto asociadas a facturas del sistema FESA.

### Flujo

```
1. GET /api/invoices/{folio}/products/{clientId}
   → Obtiene productos de factura desde FESA (XML)
   → Calcula cantidad disponible (facturada - ya devuelta)

2. POST /api/return-orders
   Body: { clientId, currency: "MXN"|"USD", chargeTypeId, notes, items: [...] }
   → Valida cantidades disponibles
   → Crea ReturnOrder + ReturnOrderItems + ReturnOrderItemHistory (en transacción)

3. POST /api/return-order-requests
   Body: { returnOrderId, requestId, returnChargePercent }
   → Vincula la devolución a una solicitud de tipo "Devolución de Material"
```

### Modelos

| Modelo | Tabla | Descripción |
|---|---|---|
| `ReturnOrder` | returnorders | Cabecera: cliente, moneda (MXN/USD), cargo, estado |
| `ReturnOrderItem` | returnorderitems | Productos: folio factura, cantidad devuelta |
| `ReturnOrderItemHistory` | returnorderitemhistory | Historial de cantidades devueltas por producto |
| `ReturnOrderRequest` | returnorderrequests | Vínculo entre ReturnOrder y Request |
| `ReturnOrderRequestItem` | returnorderrequestitems | Items con SAP ID, estado de reposición/almacén |

---

## Sistema de permisos (3 niveles)

### Nivel 1 — Módulo + Acción

Controla qué secciones del menú (sidebar) ve cada rol y qué acciones puede ejecutar.

```
POST /api/rolesPermission/assign   → Upsert de matriz rol-módulo-acción
GET  /api/rolesPermission/sidebar  → Sidebar filtrado por permisos del usuario actual
POST /api/rolesPermission/check    → Verifica permiso puntual
```

### Nivel 2 — Tipo de solicitud

Controla qué tipos de solicitud puede crear/ver cada rol.

```
POST /api/requestTypePermissions/assign
POST /api/requestTypePermissions/check
```

### Nivel 3 — Paso de workflow

`WorkflowStepPermission` permite granularidad por paso específico.

---

## Carga masiva asíncrona (Batches)

Procesa archivos Excel/CSV de forma asíncrona con jobs en cola.

### Endpoints

```
POST /api/batches          → Sube archivo + tipo; devuelve batchId
GET  /api/batches/{id}     → Estado del batch + errores por ítem
GET  /api/batches/{id}/requests → Solicitudes creadas por el batch
```

### 6 tipos de batch

| Tipo | Descripción | Columnas requeridas |
|---|---|---|
| `sapScreen` | Asocia PDFs a solicitudes | `requestNumber`, `creditNumber` |
| `creditsData` | Mapeo de créditos | `requestNumber`, `creditNumber` |
| `orderNumbers` | Vincula número de orden | `requestNumber`, `orderNumber` |
| `uploadSupport` | Adjunta archivos a rango de solicitudes | `minRange`, `maxRange` |
| `newRequest` | Crea solicitudes masivamente | `customerId`, `reasonId`, `classificationId`, ... |
| `users` | Crea usuarios masivamente | `email`, `fullName`, `roleId`, `supervisorId`, ... |

**Resolución por nombre**: los tipos `newRequest` y `users` aceptan nombres en lugar de IDs:
```
customerId: "Acme Corp"      → busca por nombre de cliente
roleId: "Admin"              → busca por roleName
supervisorId: "Juan Pérez"   → busca por fullName
```

---

## Seguridad

| Feature | Config |
|---|---|
| JWT expiración | `JWT_TTL_MINUTES` (default 120 min) |
| Bloqueo por intentos fallidos (usuario) | `SECURITY_MAX_USER_ATTEMPTS` |
| Bloqueo por IP | `SECURITY_MAX_IP_ATTEMPTS` |
| Duración de bloqueo | `SECURITY_USER_LOCK_MINUTES` |
| Requisitos de contraseña | Configurable vía `PUT /api/password-requirements` |
| Desbloqueo manual | `POST /api/security/users/{id}/unlock` |

El modelo `UserSecurity` almacena `sessionToken`, `lastIp`, `lastActivity` y contadores de intentos fallidos.

---

## Notificaciones en tiempo real

El sistema notifica vía Pusher/Reverb cuando:
- Una solicitud llega a un usuario/rol para aprobación
- Una solicitud es aprobada, rechazada o devuelta
- Operaciones masivas: se emite un resumen por usuario afectado

Para roles broadcast (REPLENISHMENT, WAREHOUSE, IT): todos los usuarios del rol reciben la notificación.

---

## Integraciones externas

| Sistema | Servicio | Uso |
|---|---|---|
| **FESA** | `FesaWsService` (NuSOAP) | Obtener XML de facturas por folio |
| **Banxico** | `BanxicoService` | Tipo de cambio MXN/USD para transiciones condicionales |
| **SendGrid** | `EmailSenderService` | Correos de bienvenida y notificaciones de flujo |

---

## Controladores

Todos en `app/Http/Controllers/Api/`:

| Controlador | Dominio |
|---|---|
| `AuthController` | Login, logout, verify, change-password |
| `RequestController` | CRUD solicitudes + todas las operaciones de flujo |
| `WorkflowController` | CRUD workflows |
| `WorkflowStepController` | CRUD pasos + transiciones condicionales |
| `UserController` | CRUD usuarios, assignments, reset password |
| `BatchController` | Creación y seguimiento de batches |
| `RoleController` | CRUD roles + equivalencias |
| `ModulePermissionController` | Matriz permisos módulo-acción |
| `RequestTypePermissionController` | Permisos por tipo de solicitud |
| `ClassificationController` | CRUD clasificaciones + sync razones |
| `CustomerController` | CRUD clientes (local + externo) |
| `ReturnOrderController` | CRUD devoluciones + historial de productos |
| `ReturnOrderRequestController` | Vínculo devolución ↔ solicitud |
| `InvoiceController` | Búsqueda de facturas (FESA) |
| `ChargePolicyController` | Políticas de cargo |
| `NotificationController` | Listado y marcado de notificaciones |
| `AdminSecurityController` | Bloqueos/desbloqueos usuario e IP |
| `DashboardController` | Métricas de dashboard |
| `ExportController` | Exportación a Excel |
| `EmailConfigController` | Configuración de email |

---

## Modelos principales

| Modelo | Tabla | Relaciones clave |
|---|---|---|
| `Request` | requests | RequestType, User, WorkflowRequestStep, Attachments |
| `Workflow` | workflows | RequestType, WorkflowStep |
| `WorkflowStep` | workflowsteps | Workflow, Role, Transitions |
| `WorkflowStepTransition` | workflowsteptransitions | WorkflowStep (from/to), condición |
| `WorkflowRequestCurrentStep` | workflowrequestcurrentsteps | Request, WorkflowStep, Role, User |
| `WorkflowRequestHistory` | workflowrequesthistory | Request, WorkflowStep, User acción |
| `User` | users | Role, Supervisor, UserAssignment |
| `Role` | roles | Users, Permissions, equivalentRole |
| `ReturnOrder` | returnorders | ReturnOrderItems, ChargeType |
| `Batch` | batches | BatchItems, User |

---

## API Resources

Transforman modelos a JSON en `app/Http/Resources/`. Un resource por modelo principal:

`RequestResource`, `WorkflowResource`, `WorkflowStepResource`, `UserResource`, `RoleResource`, `ReturnOrderResource`, `ReturnOrderRequestResource`, `BatchResource`, `BatchDetailResource`, `NotificationResource`, `CustomerResource`, `ChargePolicyResource`, `ClassificationResource`, `ModuleResource` (jerárquico), entre otros.

---

## Documentación adicional

| Archivo | Contenido |
|---|---|
| `BULK_UPLOAD_API.md` | Guía completa de carga masiva con ejemplos de payload |
| `PERMISSIONS_API.md` | Detalle del sistema de roles, módulos, acciones y permisos |
| `WORKFLOW_CONDITIONAL_FLOW_EXAMPLE.md` | Ejemplo completo de flujo con transiciones condicionales |

---

## Testing

```bash
php artisan test
```

Tests en `tests/Feature/` y `tests/Unit/`.

---

## Notas de operación

- **Rutas no aparecen**: `php artisan route:clear && php artisan config:clear`
- **Batches no procesan**: verificar que el worker de colas esté activo
- **Token inválido tras logout**: el token se invalida en `UserSecurity.sessionToken`; el middleware lo verifica en cada request
- **Usuario bloqueado**: desbloquear con `POST /api/security/users/{id}/unlock`
- **Tipo de cambio**: `BanxicoService` se consulta en tiempo real durante evaluación de transiciones condicionales con moneda mixta
