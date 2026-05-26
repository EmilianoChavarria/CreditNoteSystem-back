<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Mail\UserRegisteredMail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurity;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\EmailSenderService;
use Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UsersBatchHandler extends AbstractBatchHandler
{
    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly EmailSenderService $emailSender,
    ) {
    }

    public function batchType(): string
    {
        return 'users';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para users.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    public function process(array $row, Batch $batch): ?int
    {
        $payload = [
            'fullName' => $this->value($row, ['fullname', 'full_name']),
            'email' => $this->value($row, ['email']),
            'roleInput' => $this->normalizeNullableString($this->value($row, ['roleid', 'role_id', 'role'])),
            'roleId' => $this->resolveRoleId($row, ['roleid', 'role_id', 'role']),
            'supervisorId' => $this->resolveSupervisorId($row, ['supervisorid', 'supervisor_id', 'supervisor']),
            'clientId' => $this->normalizeNullableString($this->value($row, ['clientid', 'client_id', 'customernumber', 'customer_number'])),
            'preferredLanguage' => $this->value($row, ['preferredlanguage', 'preferred_language'], 'es'),
            'isActive' => $this->boolFromMixed($this->value($row, ['isactive', 'is_active'], true), true),
            'password' => config('bulk_upload.users.default_password', 'ChangeMe123!'),
        ];

        $validated = $this->validateRow($payload, [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', Rule::unique((new User())->getTable(), 'email')],
            'roleInput' => ['nullable', 'string', 'max:255'],
            'roleId' => ['required', 'integer', Rule::exists((new Role())->getTable(), 'id')],
            'supervisorId' => ['nullable', 'integer', Rule::exists((new User())->getTable(), 'id')],
            'clientId' => ['nullable', 'string', 'max:255'],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
            'isActive' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $this->validateCustomerAssignment($validated, $batch);

        $user = User::create([
            'fullName' => $validated['fullName'],
            'email' => $validated['email'],
            'passwordHash' => Hash::make($validated['password']),
            'roleId' => (int) $validated['roleId'],
            'supervisorId' => $validated['supervisorId'] ?? null,
            'clientId' => $validated['clientId'] ?? null,
            'preferredLanguage' => $validated['preferredLanguage'] ?? 'es',
            'isActive' => (bool) ($validated['isActive'] ?? true),
            'mustChangePassword' => true,
        ]);

        UserSecurity::create([
            'userId' => $user->id,
            'failedAttempts' => 0,
        ]);

        $this->sendWelcomeEmailIfNeeded($row, $validated);

        return null;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validateCustomerAssignment(array $validated, Batch $batch): void
    {
        $clientId = trim((string) ($validated['clientId'] ?? ''));
        $normalizedClientId = $this->normalizeCustomerNumber($clientId);

        $role = Role::query()
            ->select('id', 'roleName')
            ->find((int) $validated['roleId']);

        $isCustomerRole =
            $this->normalizeRoleName((string) $role?->roleName) === 'CUSTOMER'
            || $this->normalizeRoleName((string) ($validated['roleInput'] ?? '')) === 'CUSTOMER';

        if (!$isCustomerRole && $normalizedClientId !== '') {
            throw ValidationException::withMessages([
                'clientId' => ['Solo se permite agregar customer number cuando el rol de usuario es CUSTOMER.'],
            ]);
        }

        if (!$isCustomerRole) {
            return;
        }

        if ($normalizedClientId === '') {
            throw ValidationException::withMessages([
                'clientId' => ['El customer number es obligatorio cuando el rol de usuario es CUSTOMER.'],
            ]);
        }

        $this->ensureCustomerNumberIsNotDuplicatedInBatch($batch, $normalizedClientId);

        $customerExists = DB::connection('invoices')
            ->table('clientes_TME700618RC7')
            ->whereRaw('TRIM(CAST(idCliente AS CHAR)) = ?', [$normalizedClientId])
            ->exists();

        if (!$customerExists) {
            throw ValidationException::withMessages([
                'clientId' => ["El customer number '{$normalizedClientId}' no existe."],
            ]);
        }

        $assignedUser = User::query()
            ->select('id', 'fullName', 'email', 'clientId')
            ->whereRaw('TRIM(CAST(clientId AS CHAR)) = ?', [$normalizedClientId])
            ->first();

        if ($assignedUser) {
            throw ValidationException::withMessages([
                'clientId' => ["El customer number '{$normalizedClientId}' ya esta asignado al usuario {$assignedUser->fullName} ({$assignedUser->email})."],
            ]);
        }
    }

    private function ensureCustomerNumberIsNotDuplicatedInBatch(Batch $batch, string $clientId): void
    {
        $matches = 0;

        BatchItem::query()
            ->where('batchId', (int) $batch->id)
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$matches, $clientId) {
                foreach ($items as $item) {
                    $rawData = is_array($item->rawData)
                        ? $item->rawData
                        : (json_decode((string) $item->rawData, true) ?: []);

                    $rawClientId = $this->value($rawData, ['clientid', 'client_id', 'customernumber', 'customer_number']);

                    if ($this->normalizeCustomerNumber((string) $rawClientId) === $clientId) {
                        $matches++;
                    }
                }
            });

        if ($matches > 1) {
            throw ValidationException::withMessages([
                'clientId' => ["El customer number '{$clientId}' esta repetido dentro del archivo de carga."],
            ]);
        }
    }

    private function normalizeCustomerNumber(string $value): string
    {
        return trim($value);
    }

    private function normalizeRoleName(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $validated
     */
    private function sendWelcomeEmailIfNeeded(array $row, array $validated): void
    {
        $options = is_array($row['__welcomeEmail'] ?? null) ? $row['__welcomeEmail'] : [];

        if (($options['mode'] ?? 'none') !== 'individual') {
            return;
        }

        try {
            $this->emailSender->send(
                new UserRegisteredMail(
                    fullName: (string) $validated['fullName'],
                    email: (string) $validated['email'],
                    password: (string) $validated['password'],
                    locale: (string) ($validated['preferredLanguage'] ?? 'es')
                ),
                (string) $validated['email']
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar correo de bienvenida de usuario creado por batch', [
                'email' => (string) $validated['email'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveRoleId(array $row, array $aliases): ?int
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        // Si es numérico, buscar por ID
        if (is_numeric($value)) {
            $roleById = Role::find((int) $value);
            if ($roleById) {
                return (int) $roleById->id;
            }
        }

        // Buscar por roleName
        $role = Role::where('roleName', (string) $value)->first();
        if ($role) {
            return (int) $role->id;
        }

        // No se encontró el rol
        throw new RuntimeException("Rol no encontrado: '{$value}'");
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveSupervisorId(array $row, array $aliases): ?int
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        // Si es numérico, buscar por ID
        if (is_numeric($value)) {
            $supervisorById = User::find((int) $value);
            if ($supervisorById) {
                return (int) $supervisorById->id;
            }
        }

        // Buscar por fullName
        $supervisor = User::where('fullName', (string) $value)->first();
        if ($supervisor) {
            return (int) $supervisor->id;
        }

        // No se encontró el supervisor
        throw new RuntimeException("Supervisor (usuario) no encontrado: '{$value}'");
    }
}
