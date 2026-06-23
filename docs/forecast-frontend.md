# Forecast — Guía Frontend

Todos los endpoints requieren JWT en el header:
```
Authorization: Bearer <token>
```

---

## Roles involucrados

| Rol | Puede hacer |
|-----|-------------|
| `SALES ENGINEER` | Ver su cartera de clientes con forecast, enviar solicitudes de cambio |
| `SALES ENGINEER / MANAGER` | Aprobar/rechazar solicitudes de SE, enviar sus propias solicitudes (van directo a GM) |
| `GENERAL MANAGER` | Aprobar/rechazar solicitudes que vienen de SALES MANAGER |

---

## Endpoints

### Ver forecast por Sales Engineer

```
GET /forecast/sales-engineer/{salesEngineerId}/{year}
```

Respuesta:
```json
[
  {
    "idCliente": "C001",
    "razonSocial": "Empresa SA de CV",
    "year": 2026,
    "months": [
      {
        "month": 1,
        "amount": "180000.00",
        "modification": null
      },
      {
        "month": 2,
        "amount": "195000.00",
        "modification": {
          "id": 42,
          "proposedAmount": "181000.00",
          "status": "approved",
          "currentStep": "general_manager",
          "submittedAt": "2026-06-15T10:00:00Z"
        }
      },
      {
        "month": 3,
        "amount": "195000.00",
        "modification": {
          "id": 43,
          "proposedAmount": "85000.00",
          "status": "pending",
          "currentStep": "sales_manager",
          "submittedAt": "2026-06-15T11:00:00Z"
        }
      }
    ]
  }
]
```

- `amount`: forecast original — **nunca se modifica**, ni siquiera cuando la modificación es aprobada
- `modification`: el nuevo objetivo de ventas propuesto; `null` si no hay modificación activa (pending o approved)
- Si fue rechazado no aparece en `modification` (se ignora)
- Si hay varias modificaciones para el mismo mes, se muestra la más reciente

---

### Ver forecast de un cliente específico

```
GET /forecast/{idClient}/{year}
```

Respuesta: igual que `months[]` del endpoint de sales engineer.

> `amount` es el forecast original y **nunca cambia** aunque haya modificaciones aprobadas.
> `modification` es el dato adicional del nuevo objetivo de ventas.

---

### Guardar forecast (carga inicial / edición directa sin flujo de aprobación)

```
POST /forecast
```

Body:
```json
{
  "idClient": 100,
  "year": 2026,
  "months": [
    { "month": 1, "amount": 50000 },
    { "month": 2, "amount": 48000 }
  ]
}
```

> Usar solo para carga inicial o administración. Las ediciones de usuarios del flujo deben ir por `POST /forecast/change-requests`.

---

### Enviar solicitud de cambio de monto

```
POST /forecast/change-requests
```

Roles que pueden llamarlo: `SALES ENGINEER`, `SALES ENGINEER / MANAGER`

Body:
```json
{
  "idClient": 100,
  "year": 2026,
  "month": 3,
  "amount": 65000
}
```

Respuesta `201`:
```json
{
  "success": true,
  "data": {
    "id": 42,
    "idClient": 100,
    "year": 2026,
    "month": 3,
    "previousAmount": "50000.00",
    "proposedAmount": "65000.00",
    "status": "pending",
    "currentStep": "sales_manager",
    "submittedBy": { "id": 5, "fullName": "Juan Pérez" },
    "approver": { "id": 8, "fullName": "María García" },
    "history": [
      {
        "action": "submitted",
        "step": "sales_manager",
        "amount": "65000.00",
        "actor": { "id": 5, "fullName": "Juan Pérez" },
        "at": "2026-06-15T10:00:00Z"
      }
    ],
    "submittedAt": "2026-06-15T10:00:00Z"
  }
}
```

Errores posibles:
- `422` — Ya existe una solicitud pendiente para ese cliente/año/mes
- `422` — No se encontró aprobador disponible para ese cliente

---

### Ver solicitudes pendientes de aprobar (bandeja del aprobador)

```
GET /forecast/change-requests/pending
```

Roles: `SALES ENGINEER / MANAGER`, `GENERAL MANAGER`

Respuesta: array de solicitudes pendientes donde el usuario autenticado es el aprobador designado.

```json
[
  {
    "id": 42,
    "idClient": 100,
    "year": 2026,
    "month": 3,
    "previousAmount": "50000.00",
    "proposedAmount": "65000.00",
    "status": "pending",
    "currentStep": "sales_manager",
    "submittedBy": { "id": 5, "fullName": "Juan Pérez" },
    "approver": { "id": 8, "fullName": "María García" },
    "history": [...],
    "submittedAt": "2026-06-15T10:00:00Z"
  }
]
```

---

### Ver mis solicitudes enviadas

```
GET /forecast/change-requests/mine
```

Roles: cualquier usuario autenticado

Devuelve todas las solicitudes que el usuario envió (pending, approved, rejected), ordenadas de más reciente a más antigua.

---

### Aprobar solicitud

```
POST /forecast/change-requests/{id}/approve
```

Roles: `SALES ENGINEER / MANAGER`, `GENERAL MANAGER`

Sin body.

Respuesta `200`:
- Si venía de `SALES MANAGER`: la solicitud avanza al paso `general_manager` y se notifica al GM.
- Si venía de `GENERAL MANAGER`: el monto se aplica al forecast y la solicitud queda en `approved`.

Errores posibles:
- `403` — El usuario no es el aprobador designado de esa solicitud
- `404` — Solicitud no encontrada o ya procesada
- `422` — No hay GENERAL MANAGER disponible (solo cuando SALES MANAGER aprueba)

---

### Rechazar solicitud

```
POST /forecast/change-requests/{id}/reject
```

Roles: `SALES ENGINEER / MANAGER`, `GENERAL MANAGER`

Sin body. La solicitud queda en `rejected`. El monto original en forecast no cambia.

Errores posibles:
- `403` — El usuario no es el aprobador designado
- `404` — Solicitud no encontrada o ya procesada

---

## Valores de `modification.status`

| Valor | Significado | UI sugerida |
|-------|-------------|-------------|
| `pending` | En proceso de aprobación | Celda naranja/amarilla |
| `approved` | Aprobado como nuevo objetivo | Celda verde |
| `rejected` | Rechazado — no aparece en respuesta | — |

> El campo `amount` (forecast original) nunca cambia independientemente del status de la modificación.

## Valores de `currentStep`

| Valor | Quién debe actuar |
|-------|-------------------|
| `sales_manager` | `SALES ENGINEER / MANAGER` asignado al cliente |
| `general_manager` | `GENERAL MANAGER` del sistema |

## Valores de `action` en historial

| Valor | Significado |
|-------|-------------|
| `submitted` | Solicitud creada |
| `approved` | Aprobada en ese paso |
| `rejected` | Rechazada en ese paso |

---

## Flujo de aprobación

### Caso 1: SALES ENGINEER edita

```
SE envía solicitud
  → currentStep: "sales_manager"
  → Notificación al SALES MANAGER asignado al cliente

SALES MANAGER aprueba
  → currentStep: "general_manager"
  → Notificación al GENERAL MANAGER
  → Notificación al SE (solicitud avanzó)

GENERAL MANAGER aprueba
  → modification.status: "approved"
  → El forecast original (amount) NO se toca
  → Notificación al SE (objetivo confirmado)

SALES MANAGER o GENERAL MANAGER rechaza
  → status: "rejected"
  → Notificación al SE (rechazado)
```

### Caso 2: SALES ENGINEER / MANAGER edita

```
SALES MANAGER envía solicitud
  → currentStep: "general_manager" (salta el paso intermedio)
  → Notificación al GENERAL MANAGER

GENERAL MANAGER aprueba/rechaza
  → Notificación al SALES MANAGER que inició
```

---

## Notificaciones (socket)

El frontend debe escuchar el evento `notification.created` en el socket. El campo `type` indica el contexto:

| `type` | Destinatario | Cuándo |
|--------|-------------|--------|
| `forecast_pending_approval` | Aprobador | Nueva solicitud asignada para aprobar |
| `forecast_step_approved` | Submitter | SALES MANAGER aprobó; pasa a GENERAL MANAGER |
| `forecast_approved` | Submitter | GENERAL MANAGER aprobó; objetivo confirmado |
| `forecast_rejected` | Submitter | Solicitud rechazada en cualquier paso |

Payload del evento `notification.created`:
```json
{
  "event": "notification.created",
  "targetUserId": 5,
  "notification": {
    "id": 101,
    "userId": 5,
    "type": "forecast_pending_approval",
    "relatedId": 42,
    "title": "Tienes un forecast pendiente por aprobar",
    "message": "Se propuso un cambio de monto para el mes 3/2026 (Cliente #100). Monto propuesto: $65000.00.",
    "isRead": false,
    "readAt": null,
    "createdAt": "2026-06-15T10:00:00Z"
  },
  "sentAt": "2026-06-15T10:00:00Z"
}
```

- `relatedId` apunta al `id` de la `forecastchangerequests` — usar para navegar al detalle o refrescar la lista.
- Solo mostrar la notificación al usuario cuyo `id` coincida con `targetUserId`.

---

## Comportamiento en UI por rol

### SALES ENGINEER
- Tabla por cliente: fila **FORECAST** muestra `amount` (inmutable), fila **MODIFICACIÓN** muestra `modification.proposedAmount`.
- Si `modification === null`: celda de modificación muestra `—`.
- Si `modification.status === "pending"`: celda naranja/amarilla.
- Si `modification.status === "approved"`: celda verde.
- Puede enviar modificación si `modification === null` (llama a `POST /forecast/change-requests`).
- Sección "Mis solicitudes" → `GET /forecast/change-requests/mine`.

### SALES ENGINEER / MANAGER
- Igual que SE para sus propios envíos.
- Sección "Pendientes por aprobar" → `GET /forecast/change-requests/pending`.
- Puede aprobar o rechazar cada solicitud.

### GENERAL MANAGER
- Solo ve la sección "Pendientes por aprobar" con las solicitudes en paso `general_manager`.
- Puede aprobar o rechazar.


Usa la misma estructura de la tabla que actualmente se muestra para el forecast y distribuidores y separa por componentes (html y ts)