# Notas de Crédito - Backend API

Backend en Laravel 12 para la gestión de solicitudes (crédito/débito/auditoría/re-facturación/devolución), usuarios, roles/permisos, clientes, seguridad y cargas masivas asíncronas.

## Stack técnico

- PHP 8.2+
- Laravel 12
- JWT (`firebase/php-jwt`)
- Colas con driver `database`
- Vite + Tailwind (assets frontend básicos)

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js + npm
- Base de datos (MySQL recomendado para este proyecto)

## Instalación rápida

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

## Configuración de entorno

Variables importantes en `.env`:

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

# JWT / Seguridad
JWT_TTL_MINUTES=120
JWT_ISSUER=backend-notasCredito
JWT_SECRET=tu_secreto_jwt

SECURITY_MAX_USER_ATTEMPTS=5
SECURITY_MAX_IP_ATTEMPTS=10
SECURITY_USER_LOCK_MINUTES=1440

# Carga masiva de usuarios
BULK_USERS_DEFAULT_PASSWORD=ChangeMe123!
```

## Ejecución en desarrollo

### Opción 1: todo con un solo comando

```bash
composer run dev
```

Este comando levanta servidor, worker de cola, logs y Vite en paralelo.

### Opción 2: manual

Terminal 1:

```bash
php artisan serve
```

Terminal 2 (requerido para batches):

```bash
php artisan queue:work database --queue=default --tries=1
```

Terminal 3 (opcional para assets):

```bash
npm run dev
```

## Comandos útiles

```bash
php artisan route:list
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan test
```

## Estructura del proyecto

```text
app/
	Http/
		Controllers/Api/     # Controladores REST
		Middleware/          # JWT auth
	Jobs/                  # Procesamiento async de batches
	Models/                # Entidades Eloquent
	Services/              # Lógica de negocio (JWT, request numbers, batches)
config/
	bulk_upload.php        # Matriz de campos para carga masiva
	security.php           # Reglas de seguridad/JWT
routes/
	api.php                # Registro de módulos API
	api/*.php              # Rutas segmentadas por dominio
database/
	migrations/            # Migraciones
```

## Autenticación

La API usa middleware `jwt` para rutas protegidas.

- Header requerido: `Authorization: Bearer <token>`
- Login: `POST /api/auth/login`
- Verify token: `GET /api/auth/verify`
- Logout: `POST /api/auth/logout`

## Módulos API actuales

### Salud / público

- `GET /api/hola`

### Usuarios y seguridad

- `GET /api/me`
- `GET /api/admin/only`
- CRUD de usuarios (`/api/users`, `/api/users/{id}`, `/api/usersPag`)
- Desbloqueo de usuario/IP:
	- `POST /api/security/users/{id}/unlock`
	- `POST /api/security/ips/unlock`
- Requisitos de contraseña:
	- `GET /api/password-requirements`
	- `GET /api/password-requirements/formatted`
	- `POST /api/password-requirements/validate`
	- `PUT /api/password-requirements`

### Catálogos y permisos

- CRUD de roles: `/api/roles`
- CRUD de módulos: `/api/modules`
- Permisos de rol-módulo:
	- `POST /api/rolesPermission/assign`
	- `GET /api/rolesPermission`
- Tipos de solicitud: `/api/requestType`
- Clasificaciones:
	- `POST /api/classifications`
	- `GET /api/classifications/requestType/{typeRequestId}`
- Clientes:
	- `GET /api/customers`
	- `GET /api/customers/search`
	- `GET /api/customers/{id}`
	- `POST /api/customers`
	- `PUT /api/customers/{id}`
	- `DELETE /api/customers/{id}`

### Solicitudes y dashboard

- `GET /api/requests`
- `GET /api/requests/reasons`
- `GET /api/requests/next-number/{requestTypeId}`
- `GET /api/requests/{id}` (filtra por tipo)
- `POST /api/requests/newRequest`
- `GET /api/dashboard`

### Workflows

- `GET /api/workflows`
- `GET /api/workflows/{id}`
- `POST /api/workflows`
- `PUT /api/workflows/{id}`
- `DELETE /api/workflows/{id}`

### Carga masiva (asíncrona)

- `POST /api/batches`
- `GET /api/batches/{id}`

Documentación detallada: ver `BULK_UPLOAD_API.md`.

## Carga masiva: tipos soportados

`sapScreen`, `creditsData`, `orderNumbers`, `uploadSupport`, `newRequest`, `users`

El procesamiento se hace con jobs:

- `ProcessBatchJob`
- `ProcessBatchItemJob`

## Convención de respuesta API

Las respuestas se centralizan en `App\Support\ApiResponse` con formato uniforme para éxito/error.

## Testing

Ejecutar pruebas:

```bash
php artisan test
```

Actualmente existe la base de pruebas de ejemplo en `tests/Feature` y `tests/Unit`.

## Notas de operación

- Si no aparecen rutas nuevas, limpiar caché de rutas:

```bash
php artisan route:clear
```

- Para cargas masivas, verifica que el worker de colas esté activo.

