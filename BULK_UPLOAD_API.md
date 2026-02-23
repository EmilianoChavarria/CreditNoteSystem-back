# Bulk Upload API (Backend)

Este documento explica cómo consumir el módulo de carga masiva por API.

## Autenticación

Todos los endpoints requieren middleware `jwt`.

- Header requerido:
  - `Authorization: Bearer <token>`

---

## 1) Crear batch

- **Método:** `POST`
- **Endpoint:** `/api/batches`
- **Content-Type:** `multipart/form-data`

### Campos base (form-data)

- `batchType` (string, requerido)
  - Valores: `sapScreen`, `creditsData`, `orderNumbers`, `uploadSupport`, `newRequest`, `users`
- `file` (archivo o arreglo de archivos, requerido)
  - Para múltiples archivos usa `file[]`
- `requestTypeId` (int, requerido en `uploadSupport` y `newRequest`)
- `minRange` (int, requerido en `uploadSupport`)
- `maxRange` (int, requerido en `uploadSupport`)

---

## 2) Consultar progreso y errores

- **Método:** `GET`
- **Endpoint:** `/api/batches/{id}`
- **Query params opcionales:**
  - `perPage` (int, default 25, min 1, max 200)

### Respuesta esperada

Incluye:

- `batch`: datos del batch + `progressPercent`
- `itemsSummary`: conteo por estado (`pending`, `processing`, `success`, `error`)
- `errors`: errores acumulados desde `batchItems` con paginación

---

## 3) ¿Cómo se ejecuta el job?

El procesamiento del batch es asíncrono.

Cuando haces `POST /api/batches`, se encola:

- `ProcessBatchJob` (dispara los jobs por fila)
- `ProcessBatchItemJob` (procesa cada registro)

Para que se ejecuten, debes tener un worker de cola corriendo.

### Comando recomendado

```bash
php artisan queue:work database --queue=default --tries=1
```

### Alternativa (desarrollo)

```bash
php artisan queue:listen --queue=default --tries=1
```

### Flujo sugerido

1. Levantar worker de cola.
2. Enviar `POST /api/batches`.
3. Consultar avance con `GET /api/batches/{id}`.

### Verificación rápida

- Si el worker está activo, `processedRecords` y `processingRecords` cambian en `batches`.
- Los errores por fila se reflejan en `batchItems.errorLog` y en la respuesta de `GET /api/batches/{id}`.

---

## Form-data por tipo de carga

## A) sapScreen

Sube archivos individuales con nombre:

- `1924_3254.pdf`

Donde:
- `1924` = `requestNumber`
- `3254` = `creditNumber`

### Ejemplo

- `batchType`: `sapScreen`
- `file[]`: `1924_3254.pdf`
- `file[]`: `2001_8888.pdf`

---

## B) creditsData

Archivo CSV/XML/XLS/XLSX con columnas:

- `requestNumber`
- `creditNumber`

### Ejemplo

- `batchType`: `creditsData`
- `file`: `credits_data.xlsx`

---

## C) orderNumbers

Archivo CSV/XML/XLS/XLSX con columnas:

- `requestNumber`
- `orderNumber`

### Ejemplo

- `batchType`: `orderNumbers`
- `file`: `orders.csv`

---

## D) uploadSupport

Adjunta uno o varios archivos a todas las requests del rango.

### Requeridos

- `batchType`: `uploadSupport`
- `requestTypeId`
- `minRange`
- `maxRange`
- `file[]` (uno o varios)

### Ejemplo

- `batchType`: `uploadSupport`
- `requestTypeId`: `2`
- `minRange`: `1000`
- `maxRange`: `1200`
- `file[]`: `soporte_1.pdf`
- `file[]`: `soporte_2.pdf`

---

## E) newRequest

Archivo CSV/XML/XLS/XLSX con datos para crear requests nuevas.

### Requeridos

- `batchType`: `newRequest`
- `requestTypeId`
- `file`

### Columnas esperadas (base)

- `userId` (opcional, si no viene usa usuario autenticado)
- `requestDate`
- `currency`
- `customerId` ⭐ (puede ser ID o nombre de cliente)
- `area` (opcional)
- `reasonId` ⭐ (puede ser ID o nombre de razón)
- `classificationId` ⭐ (puede ser ID o nombre de clasificación)
- `deliveryNote` (opcional)
- `invoiceNumber` (opcional)
- `invoiceDate` (opcional)
- `exchangeRate` (opcional)
- `creditNumber` (opcional)
- `amount`
- `hasIva` (opcional; default true)
- `orderNumber` (opcional)

### Búsqueda de valores por nombre (⭐)

En lugar de usar IDs, puedes usar nombres:

#### customerId

Puedes poner cualquiera de estos en la columna:
- **ID del cliente** (ej: `42`)
- **Número de cliente** (ej: `C001`, `10050`)
- **Nombre del cliente** (ej: `Acme Corporation`)

El sistema busca automáticamente en este orden:
1. Por ID directo
2. Por customerNumber
3. Por customerName

#### reasonId

Puedes poner:
- **ID de la razón** (ej: `5`)
- **Nombre de la razón** (ej: `Devolución por defecto`, `Crédito administrativo`)

#### classificationId

Puedes poner:
- **ID de la clasificación** (ej: `3`)
- **Nombre de la clasificación** (ej: `Urgente`, `Normal`, `Prioritario`)

### Validación y errores

Si algún dato no se encuentra (ej: "Cliente XYZ" no existe), la fila se registra como **error** con un mensaje en `errorLog`:

```json
{
  "status": "error",
  "errorLog": {
    "message": "Cliente no encontrado: 'XYZ'",
    "type": "RuntimeException"
  }
}
```

Verifica los errores en `GET /api/batches/{id}` en la sección `errors.data`.

### Cálculo automático

- `IVA = amount * 0.16` si `hasIva=true`
- `totalAmount = amount + IVA`
- `requestNumber` se asigna automáticamente con el `id` creado

### Reglas por tipo de request

Puedes configurar la matriz de campos por módulo en:

- `config/bulk_upload.php`
- sección: `new_request.modules`

---

## F) users

Archivo CSV/XML/XLS/XLSX con columnas:

- `fullName`
- `email`
- `roleId` ⭐ (puede ser ID o nombre del rol)
- `supervisorId` ⭐ (opcional; puede ser ID o nombre del usuario)
- `preferredLanguage` (`en` o `es`, opcional)
- `isActive` (opcional)
- `password` (opcional)

### Búsqueda de valores por nombre (⭐)

En lugar de usar IDs, puedes usar nombres:

#### roleId

Puedes poner:
- **ID del rol** (ej: `1`, `2`)
- **Nombre del rol** (ej: `Admin`, `Gerente`, `Operador`)

El sistema busca automáticamente en este orden:
1. Por ID directo
2. Por roleName

#### supervisorId

Puedes poner:
- **ID del usuario** (ej: `5`, `12`)
- **Nombre completo del supervisor** (ej: `Juan Pérez`, `María García`)

El sistema busca automáticamente en este orden:
1. Por ID directo
2. Por fullName

### Validación y errores

Si algún dato no se encuentra (ej: "Rol XYZ" no existe), la fila se registra como **error**:

```json
{
  "status": "error",
  "errorLog": {
    "message": "Rol no encontrado: 'XYZ'",
    "type": "RuntimeException"
  }
}
```

Lo mismo aplica para `supervisorId`:

```json
{
  "status": "error",
  "errorLog": {
    "message": "Supervisor (usuario) no encontrado: 'XYZ'",
    "type": "RuntimeException"
  }
}
```

Verifica los errores en `GET /api/batches/{id}` en la sección `errors.data`.

### Contraseña

Si no viene `password`, se usa la contraseña por defecto de:

- `config/bulk_upload.php` → `users.default_password`

---

## Ejemplos con cURL

## Crear batch (creditsData)

```bash
curl -X POST "http://localhost:8000/api/batches" \
  -H "Authorization: Bearer TU_TOKEN" \
  -F "batchType=creditsData" \
  -F "file=@C:/files/credits_data.csv"
```

## Crear batch (newRequest con nombres en lugar de IDs)

```bash
curl -X POST "http://localhost:8000/api/batches" \
  -H "Authorization: Bearer TU_TOKEN" \
  -F "batchType=newRequest" \
  -F "requestTypeId=1" \
  -F "file=@C:/files/new_requests.xlsx"
```

**Contenido del Excel (new_requests.xlsx):**

| requestDate | currency | customerId      | reasonId           | classificationId | amount   |
|-------------|----------|-----------------|-------------------|-----------------|----------|
| 2026-02-23  | USD      | Acme Corp       | Devolución        | Urgente         | 5000     |
| 2026-02-23  | USD      | 42              | Crédito Admin      | Normal          | 3000     |
| 2026-02-23  | MXN      | C001            | Falta             | Prioritario     | 1500     |

✅ El sistema buscará automáticamente:
- Cliente por nombre ("Acme Corp"), por ID (42) o por número ("C001")
- Razón por nombre ("Devolución", "Crédito Admin", "Falta")
- Clasificación por nombre ("Urgente", "Normal", "Prioritario")

❌ Si alguno no existe, la fila se marca como **error** con el detalle en `errorLog`.

## Crear batch (uploadSupport con múltiples archivos)

```bash
curl -X POST "http://localhost:8000/api/batches" \
  -H "Authorization: Bearer TU_TOKEN" \
  -F "batchType=uploadSupport" \
  -F "requestTypeId=2" \
  -F "minRange=1000" \
  -F "maxRange=1200" \
  -F "file[]=@C:/files/soporte_1.pdf" \
  -F "file[]=@C:/files/soporte_2.pdf"
```

## Crear batch (users con nombres de rol y supervisor)

```bash
curl -X POST "http://localhost:8000/api/batches" \
  -H "Authorization: Bearer TU_TOKEN" \
  -F "batchType=users" \
  -F "file=@C:/files/users.xlsx"
```

**Contenido del Excel (users.xlsx):**

| fullName     | email              | roleId    | supervisorId    | isActive |
|-------------|-------------------|-----------|-----------------|----------|
| Juan Pérez   | juan@example.com   | Admin     | María García    | 1        |
| Carlos López | carlos@example.com | Operador  | 5               | 1        |
| Ana Martín   | ana@example.com    | 2         | Juan Pérez      | 1        |

✅ El sistema buscará automáticamente:
- Rol por nombre ("Admin", "Operador") o por ID (2)
- Supervisor por nombre ("María García", "Juan Pérez") o por ID (5)

## Consultar batch

```bash
curl -X GET "http://localhost:8000/api/batches/15?perPage=50" \
  -H "Authorization: Bearer TU_TOKEN"
```

**Respuesta de ejemplo:**

```json
{
  "success": true,
  "message": "Estado del batch",
  "data": {
    "batch": {
      "id": 15,
      "batchType": "newRequest",
      "status": "processing",
      "totalRecords": 100,
      "processedRecords": 75,
      "errorRecords": 5,
      "progressPercent": 75.0
    },
    "itemsSummary": {
      "pending": 20,
      "processing": 0,
      "success": 75,
      "error": 5
    },
    "errors": {
      "data": [
        {
          "id": 42,
          "rawData": {
            "customerId": "ClienteNoExiste",
            "amount": 1000
          },
          "errorLog": {
            "message": "Cliente no encontrado: 'ClienteNoExiste'",
            "type": "RuntimeException"
          }
        }
      ],
      "pagination": {
        "currentPage": 1,
        "perPage": 50,
        "total": 5,
        "lastPage": 1
      }
    }
  }
}
```

---

## Notas importantes

- Los archivos se almacenan en `storage/app/batches/...`
- El procesamiento es asíncrono con queue (`database` driver)
- Cada fila se registra en `batchItems` con `status` y `errorLog`
- El progreso global se actualiza en `batches`
- No se crearon migraciones de `batches` ni `batchItems`

---

## Búsqueda inteligente de valores por nombre

### ¿Qué es?

En lugar de requerir IDs numéricos, el sistema permite usar **nombres descriptivos** en archivos Excel/CSV para los campos relacionados.

### Campos que soportan búsqueda por nombre

| Batch Type | Campo | Búsqueda por |
|-----------|-------|-------------|
| `newRequest` | `customerId` | ID, customerNumber, **customerName** |
| `newRequest` | `reasonId` | ID, **name** |
| `newRequest` | `classificationId` | ID, **name** |
| `users` | `roleId` | ID, **roleName** |
| `users` | `supervisorId` | ID, **fullName** |

### Ventajas

✅ No necesitas memorizar IDs  
✅ Excel más legible y mantenible  
✅ Reducción de errores por ID incorrecto  
✅ Compatible con IDs (puedes mezclar nombres e IDs en el mismo archivo)  

### Manejo de errores

Si un nombre no existe en la base de datos, la fila se marca como **error** con un mensaje específico en `errorLog`:

```json
{
  "message": "Cliente no encontrado: 'NombreQueNoPuso'",
  "type": "RuntimeException"
}
```

Consulta los errores en: `GET /api/batches/{id}` → sección `errors.data`

---

## Troubleshooting

### Error: Table `jobs` doesn't exist

Significa que la cola está configurada con driver `database` y faltan tablas base de Laravel en MySQL.

### Error: Table `cache` doesn't exist

Significa que `CACHE_STORE=database` y falta la tabla `cache`.

### Solución

Ejecuta migraciones pendientes para crear tablas base (`jobs`, `failed_jobs`, `cache`, etc.) y luego vuelve a levantar el worker:

```bash
php artisan migrate
php artisan queue:work database --queue=default --tries=1
```

### Si no quieres cache en BD en local

Puedes usar en `.env`:

- `CACHE_STORE=file`

y limpiar configuración:

```bash
php artisan config:clear
```
