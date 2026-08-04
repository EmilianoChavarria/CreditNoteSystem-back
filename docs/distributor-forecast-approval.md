# Distributor Forecast — Flujo de aprobación

Documentación para frontend de lo agregado en esta sesión: campos de asignación en `distributors`, captura manual de forecast/sales, y el flujo de aprobación de "SALES TARGET MODIFICATION" para clientes extranjeros (distribuidores).


---

## 1. Distributor — campos de asignación

`distributors` ahora tiene `salesEngineerId` / `salesManagerId` (FK a `users`, nullable). Se mandan en el mismo `PUT` de siempre:

```
PUT /api/distributors/{clientNumber}
```

```ts
interface UpdateDistributorPayload {
  businessName?: string;
  taxId?: string;
  countrycode?: string;
  address?: string;
  emails?: string;
  salesEngineerId?: number | null;
  salesManagerId?: number | null;
}
```

Response (`DistributorResource`):

```ts
interface DistributorResponse {
  id: number;
  businessName: string;
  taxId: string;
  address: string;
  emails: string;
  clientNumber: string;
  countrycode: string;
  salesEngineerId: number | null;
  salesEngineerName: string | null; // null si no hay asignado
  salesManagerId: number | null;
  salesManagerName: string | null;
}
```

### Dropdowns para asignar

```
GET /api/users/sales-engineers   // rol exacto SALES ENGINEER
GET /api/users/sales-managers    // rol exacto SALES ENGINEER / MANAGER
```

```ts
interface SalesUserOption { id: number; fullName: string; role: string; }
```
Response: `ApiEnvelope<SalesUserOption[]>`.

### Carga masiva (Excel/CSV)

Reusa el sistema genérico de batches ya existente (`POST /api/batches`, mismo que usan `users`/`forecast`/etc.) — no es un endpoint nuevo, es un `batchType` nuevo: **`distributors`**.

```
POST /api/batches
Content-Type: multipart/form-data

batchType=distributors
file=<archivo.xlsx|.csv|.xls|.xml|.txt>
```

Columnas del archivo (nombres flexibles — no importan mayúsculas/espacios/guiones, ej. "Client Number", "client_number" y "clientnumber" son equivalentes):

| Columna | Alias aceptados | Requerido |
|---|---|---|
| Client Number | `clientNumber`, `client_number` | Sí |
| Business Name | `businessName`, `business_name`, `razonSocial`, `razon_social` | Sí |
| Tax Id | `taxId`, `tax_id`, `rfc` | Sí |
| Address | `address`, `direccion`, `domicilio` | Sí |
| Emails | `emails`, `correos`, `email` | Sí |
| Country Code | `countrycode`, `country_code`, `pais`, `país` | Sí |
| Sales Engineer | `salesEngineer`, `sales_engineer`, `salesEngineerId` | No |
| Sales Manager | `salesManager`, `sales_manager`, `salesManagerId` | No |

**Sales Engineer / Sales Manager**: se puede mandar el **nombre completo** (`fullName` exacto en `users`) o el `id` numérico directamente — el backend lo resuelve. Si el nombre no coincide con ningún usuario, esa fila específica falla (no tumba el batch completo, ver abajo).

**No es upsert** — es solo-creación. Si el `clientNumber` de una fila ya existe en `distributors`, esa fila **falla** (`"El distribuidor con clientNumber 'X' ya existe."`, va a `errorLog`, no toca el registro existente). También valida que el mismo `clientNumber` no esté repetido dos veces dentro del propio archivo (`"...está repetido dentro del archivo de carga."`, ambas filas fallan). El `PUT /api/distributors/{clientNumber}` individual sigue siendo upsert, sin cambios — esto solo aplica a la carga masiva.

**Response (201)** — igual que cualquier batch:
```json
{
  "codeStatus": 201,
  "success": true,
  "message": "Batch creado y encolado",
  "data": {
    "id": 42,
    "fileName": "distribuidores.xlsx",
    "batchType": "distributors",
    "status": "processing",
    "totalRecords": 50,
    "processedRecords": 0,
    "processingRecords": 0,
    "errorRecords": 0,
    "progressPercent": 0,
    "createdAt": "..."
  },
  "errors": null,
  "timestamp": "..."
}
```

Procesamiento es asíncrono (cola). Para ver progreso y errores por fila:
```
GET /api/batches/{id}
```
`data.itemsSummary` trae conteos por status (`pending`/`processing`/`success`/`error`) y `data.errors.data` trae el detalle de las filas que fallaron (ej. `"Sales Engineer no encontrado: 'Juan Pérez'"`, o validación de campo requerido faltante), paginado.

---

## 2. Captura manual de forecast / sales

El `forecast` (meta) y `sales` (venta real) de un distribuidor se capturan a mano — no hay query de facturación como en el forecast normal. Solo **FORECAST ADMIN** puede escribir; lectura abierta a cualquier jwt.

```ts
interface DistributorForecastMonth {
  month: number;
  forecast: number | null;
  sales: number | null;
  modification: {
    id: number;
    proposedForecast: number;
    status: 'pending' | 'approved' | 'rejected';
    currentStep: 'sales_manager' | 'general_manager' | 'auto_approved';
    submittedAt: string;
  } | null; // null si no hay ninguna modificación pending/approved para ese mes
}
```

### `GET /api/distributors/{distributorId}/forecast/{year}`
`data: DistributorForecastMonth[]` — solo meses con forecast/sales capturado o con alguna modificación asociada.

### `POST /api/distributors/{distributorId}/forecast` (201)
Upsert anual, `forecast`/`sales` requeridos por mes:
```json
{ "year": 2026, "months": [{ "month": 1, "forecast": 5000, "sales": 4200 }] }
```

### `PUT /api/distributors/{distributorId}/forecast/{year}/{month}` (200)
Update parcial de un mes — manda `forecast`, `sales` o ambos:
```json
{ "forecast": 5000 }
```

Ambos regresan `DistributorForecastMonth[]` (POST) o `DistributorForecastMonth` (PUT). `modification` ahora viene **poblado de verdad** cuando hay una solicitud pending/approved para ese mes (antes siempre era `null`).

### `GET /api/distributors/sales-engineer/{salesEngineerId}/{year}`

Todos los distribuidores asignados a un sales engineer (`distributors.salesEngineerId`), para ese año — pensado para reusar **el mismo componente de tabla** que ya usan en el listado de clientes extranjeros (`GET /api/forecast/sales-engineer/{salesEngineerId}/{year}`, el link que mandaste). Mismo shape de respuesta que ese endpoint, campo por campo (`isGroup`, `idCliente`, `razonSocial`, `year`, `months`) — la única diferencia es que aquí `idCliente` es el `id` del distribuidor (no un `idCliente` externo) y `razonSocial` es el `businessName` del distribuidor. `isGroup` siempre viene `false` (los distribuidores no tienen el concepto de "grupo de clientes" que sí existe en el forecast normal).

```ts
interface DistributorSalesEngineerEntry {
  isGroup: false;
  idCliente: number;       // id del distribuidor
  razonSocial: string;     // businessName del distribuidor
  year: number;
  months: DistributorForecastMonth[]; // mismo shape que la sección de arriba
}
```

```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Distribuidores con forecast",
  "data": [
    {
      "isGroup": false,
      "idCliente": 8,
      "razonSocial": "DILCO",
      "year": 2026,
      "months": [
        { "month": 1, "forecast": 5000, "sales": 4200, "modification": null }
      ]
    }
  ],
  "errors": null,
  "timestamp": "..."
}
```

Si `salesEngineerId` no tiene distribuidores asignados, `data` regresa `[]` (no 404). No requiere rol específico, solo jwt.

---

## 3. Flujo de aprobación — "SALES TARGET MODIFICATION"

Mismo mecanismo que el forecast normal (`ForecastChangeRequest`), aplicado a distribuidores. Solo el **forecast** (meta) pasa por aprobación — `sales` se sigue editando directo con los endpoints de la sección 2, sin aprobación.

### Roles y máquina de estados

| Quién propone | Va a | Aprobador |
|---|---|---|
| `FORECAST ADMIN` | Auto-aprobado, se aplica de inmediato | — (no pasa por nadie) |
| `SALES ENGINEER` | `sales_manager` | `distributors.salesManagerId` del distribuidor |
| `SALES ENGINEER / MANAGER` | `general_manager` | el único usuario activo con rol `GENERAL MANAGER` |

En el paso `sales_manager`, al aprobar, la solicitud **escala** a `general_manager` (no se aplica todavía). En `general_manager` (aprobación final), se actualiza `DistributorForecast.forecast` — `sales` no se toca.

`status`: `pending` → `approved` | `rejected`. Si se rechaza en cualquier paso, no se aplica nada y `modification` deja de aparecer en el `GET` de la sección 2 (solo se muestra `pending`/`approved`).

### Permisos

- **Proponer** (`POST .../change-requests`): `SALES ENGINEER`, `SALES ENGINEER / MANAGER`, o `FORECAST ADMIN`.
- **Aprobar/Rechazar** (`.../approve`, `.../reject`): `SALES ENGINEER / MANAGER` o `GENERAL MANAGER` — pero solo el usuario específico asignado como `approverUserId` de esa solicitud (403 si no eres tú).

### Endpoints

#### `POST /api/distributors/forecast/change-requests`
Propone un cambio de forecast para un mes.

```ts
interface StoreChangeRequestPayload {
  distributorId: number;
  year: number;       // 2000-2100
  month: number;       // 1-12
  forecast: number;    // entero >= 0, valor propuesto
}
```

**201** — `data`: el `DistributorForecastChangeRequest` crudo con relaciones cargadas (`history`, `submittedBy`, `approver`):
```json
{
  "codeStatus": 201,
  "success": true,
  "message": "Solicitud de cambio enviada",
  "data": {
    "id": 1,
    "distributorId": 8,
    "year": 2026,
    "month": 1,
    "previousForecast": 0,
    "proposedForecast": 5000,
    "status": "pending",
    "currentStep": "sales_manager",
    "approverUserId": 6,
    "submittedByUserId": 10,
    "createdAt": "2026-07-22T17:45:00.000000Z",
    "updatedAt": "2026-07-22T17:45:00.000000Z",
    "submittedBy": { "id": 10, "fullName": "..." },
    "approver": { "id": 6, "fullName": "..." },
    "history": [
      { "id": 1, "distributorForecastChangeRequestId": 1, "action": "submitted", "actorUserId": 10, "forecast": 5000, "step": "sales_manager", "createdAt": "..." }
    ]
  },
  "errors": null,
  "timestamp": "..."
}
```

Errores: **401** no autenticado · **403** rol sin permiso para proponer · **404** distribuidor no existe · **422** ya hay una solicitud `pending` para ese distribuidor/año/mes, o no se encontró aprobador (`salesManagerId` sin asignar / no hay `GENERAL MANAGER` activo).

#### `GET /api/distributors/forecast/change-requests/pending`
Solicitudes pendientes de aprobar **por el usuario autenticado** (según `approverUserId`). Requiere rol de aprobador (403 si no). `data`: array con shape "formateado" (distinto al de `submit`, ver abajo).

#### `GET /api/distributors/forecast/change-requests/mine`
Solicitudes que **yo propuse** (cualquier status), sin restricción de rol.

#### `GET /api/distributors/forecast/change-requests/history?distributorId=8&year=2026&month=1`
Historial completo (todas las solicitudes, cualquier status) de un distribuidor/año/mes.

Estos 3 regresan el mismo shape "formateado" (`data: ChangeRequestSummary[]`):

```ts
interface ChangeRequestSummary {
  id: number;
  distributorId: number;
  year: number;
  month: number;
  previousForecast: number;
  proposedForecast: number;
  status: 'pending' | 'approved' | 'rejected';
  currentStep: 'sales_manager' | 'general_manager' | 'auto_approved';
  distributor: { id: number; businessName: string } | null;
  submittedBy: { id: number; fullName: string } | null;
  approver: { id: number; fullName: string } | null;
  history: Array<{
    action: 'submitted' | 'approved' | 'rejected' | 'auto_approved';
    step: 'sales_manager' | 'general_manager' | 'auto_approved';
    forecast: number;
    actor: { id: number; fullName: string } | null;
    at: string;
  }>;
  submittedAt: string;
}
```

Ejemplo (`pending`):
```json
{
  "codeStatus": 200,
  "success": true,
  "message": "Solicitudes pendientes de aprobación",
  "data": [
    {
      "id": 1,
      "distributorId": 8,
      "year": 2026,
      "month": 1,
      "previousForecast": 0,
      "proposedForecast": 5000,
      "status": "pending",
      "currentStep": "sales_manager",
      "distributor": { "id": 8, "businessName": "DILCO" },
      "submittedBy": { "id": 10, "fullName": "..." },
      "approver": { "id": 6, "fullName": "..." },
      "history": [
        { "action": "submitted", "step": "sales_manager", "forecast": 5000, "actor": { "id": 10, "fullName": "..." }, "at": "2026-07-22T17:45:00.000000Z" }
      ],
      "submittedAt": "2026-07-22T17:45:00.000000Z"
    }
  ],
  "errors": null,
  "timestamp": "..."
}
```

**Nota**: el shape de `submit` (crudo, camelCase de columnas) y el de `pending`/`mine`/`history` (formateado) **no son iguales** — mismo patrón que ya existe en el forecast normal. Si al frontend le conviene un solo shape, avisar y lo unifico.

#### `POST /api/distributors/forecast/change-requests/{id}/approve`
Sin body. Si el paso era `sales_manager`, escala a `general_manager` (sigue `pending`):
```json
{ "codeStatus": 200, "success": true, "message": "Aprobado por SALES MANAGER, pendiente de GENERAL MANAGER", "data": null, "errors": null, "timestamp": "..." }
```
Si el paso era `general_manager` (final), aplica el cambio:
```json
{ "codeStatus": 200, "success": true, "message": "Objetivo aprobado y aplicado al forecast del distribuidor", "data": null, "errors": null, "timestamp": "..." }
```
Errores: **401**, **403** (no eres el `approverUserId` designado, o tu rol no aprueba), **404** (no existe o ya no está `pending`), **422** (no hay `GENERAL MANAGER` activo al momento de escalar).

#### `POST /api/distributors/forecast/change-requests/{id}/reject`
Sin body. Mismos códigos de error que `approve`.
```json
{ "codeStatus": 200, "success": true, "message": "Solicitud rechazada", "data": null, "errors": null, "timestamp": "..." }
```

---

## 4. Notificaciones y correos

- In-app (socket + tabla `notifications`): 4 tipos nuevos — `distributor_forecast_pending_approval`, `distributor_forecast_step_approved`, `distributor_forecast_approved`, `distributor_forecast_rejected`.
- Correos: se reusan las plantillas del forecast normal (`ForecastPendingApprovalMail`, `ForecastFinalApprovedMail`, `ForecastRequestApprovedMail`, `ForecastRejectedMail`) — mismo diseño, dato cosmético: el label del correo dice "Cliente" en vez de "Distribuidor" y muestra el `id` interno del distribuidor, no el `clientNumber`. Avisar si se quiere ajustar el texto.
- El correo de aprobación final llega a los `emails` del propio distribuidor (los mismos que se ven/editan en la ficha del distribuidor).

---

## Fuera de alcance (por ahora)

- Recordatorios automáticos de solicitudes viejas (sí existen para el forecast normal, se puede agregar después).
- Endpoint de borrado de una solicitud/mes.
