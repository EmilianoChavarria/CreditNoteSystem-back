<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurity;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use Hash;
use RuntimeException;
use Illuminate\Validation\Rule;

class UsersBatchHandler extends AbstractBatchHandler
{
    public function __construct(private readonly BulkFileParser $fileParser)
    {
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
            'roleId' => $this->resolveRoleId($row, ['roleid', 'role_id']),
            'supervisorId' => $this->resolveSupervisorId($row, ['supervisorid', 'supervisor_id']),
            'preferredLanguage' => $this->value($row, ['preferredlanguage', 'preferred_language'], 'es'),
            'isActive' => $this->boolFromMixed($this->value($row, ['isactive', 'is_active'], true), true),
            'password' => $this->value($row, ['password'], config('bulk_upload.users.default_password', 'ChangeMe123!')),
        ];

        $validated = $this->validateRow($payload, [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', Rule::unique((new User())->getTable(), 'email')],
            'roleId' => ['required', 'integer', Rule::exists((new Role())->getTable(), 'id')],
            'supervisorId' => ['nullable', 'integer', Rule::exists((new User())->getTable(), 'id')],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
            'isActive' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $user = User::create([
            'fullName' => $validated['fullName'],
            'email' => $validated['email'],
            'passwordHash' => Hash::make($validated['password']),
            'roleId' => (int) $validated['roleId'],
            'supervisorId' => $validated['supervisorId'] ?? null,
            'preferredLanguage' => $validated['preferredLanguage'] ?? 'es',
            'isActive' => (bool) ($validated['isActive'] ?? true),
        ]);

        UserSecurity::create([
            'userId' => $user->id,
            'failedAttempts' => 0,
        ]);

        return null;
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
