<?php

namespace App\Services;

use App\Models\User;
use App\Services\Batches\Parsers\BulkFileParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CustomerQueryService
{
    private const CONNECTION = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';
    private const MANAGER_COLUMNS = [
        'csLeaderId',
        'processorId',
        'salesEngineerId',
        'salesManagerId',
        'financeManagerId',
        'marketingManagerId',
        'customerServiceManagerId',
    ];

    private array $userCache = [];

    public function __construct(private readonly BulkFileParser $fileParser)
    {
    }

    /**
     * Carga masiva de correos de recordatorio de devoluciones.
     * Columnas: número de cliente + correos separados por ";" (o ",").
     * Reemplaza los correos guardados del cliente. Cada fila se procesa de forma independiente.
     *
     * @return array{total: int, updated: int, failed: int, errors: array<int, array{row: int, customerNumber: ?string, message: string}>}
     */
    public function bulkUpdateReturnsEmails(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $storedPath = $file->store('tmp/returns-emails-bulk', 'local');

        $result = ['total' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        $seen = [];

        try {
            foreach ($this->fileParser->parseByStoredFile($storedPath, $extension) as $row) {
                $result['total']++;
                $rowNumber = (int) ($row['_rowNumber'] ?? $result['total'] + 1);
                $customerNumber = null;

                try {
                    $customerNumber = $this->firstValue($row, ['customer_number', 'customernumber', 'client_number', 'clientnumber', 'idcliente', 'id_cliente', 'numero_cliente', 'numero_de_cliente', 'cliente']);
                    $rawEmails = $this->firstValue($row, ['emails', 'email', 'correos', 'correo']);

                    if ($customerNumber === null) {
                        throw new \RuntimeException('Falta el número de cliente.');
                    }

                    if (isset($seen[$customerNumber])) {
                        throw new \RuntimeException("El cliente '{$customerNumber}' está repetido en el archivo (fila {$seen[$customerNumber]}).");
                    }
                    $seen[$customerNumber] = $rowNumber;

                    $emails = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', (string) $rawEmails) ?: []))));

                    if (count($emails) === 0) {
                        throw new \RuntimeException('Debe indicar al menos un correo electrónico.');
                    }

                    foreach ($emails as $email) {
                        if (Validator::make(['e' => $email], ['e' => ['email']])->fails()) {
                            throw new \RuntimeException("Correo inválido: '{$email}'.");
                        }
                    }

                    $exists = DB::connection(self::CONNECTION)
                        ->table(self::CLIENT_TABLE)
                        ->where('idCliente', $customerNumber)
                        ->exists();

                    if (!$exists) {
                        throw new \RuntimeException("El cliente '{$customerNumber}' no existe.");
                    }

                    $this->updateReturnsEmails((int) $customerNumber, $emails);
                    $result['updated']++;
                } catch (\Throwable $e) {
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'customerNumber' => $customerNumber,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } finally {
            Storage::disk('local')->delete($storedPath);
        }

        return $result;
    }

    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    /**
     * Guarda los correos de recordatorio de política de devoluciones de un cliente.
     *
     * @param string[] $emails
     */
    public function updateReturnsEmails(int $idCliente, array $emails): void
    {
        if (!Schema::connection(self::CONNECTION)->hasColumn(self::CLIENT_EXT_TABLE, 'correosForecast')) {
            throw new \RuntimeException('La columna correosForecast no existe en ' . self::CLIENT_EXT_TABLE . '.');
        }

        $value = implode(';', $emails);
        $clienteId = (string) $idCliente;

        $exists = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_EXT_TABLE)
            ->where('idCliente', $clienteId)
            ->exists();

        if ($exists) {
            DB::connection(self::CONNECTION)
                ->table(self::CLIENT_EXT_TABLE)
                ->where('idCliente', $clienteId)
                ->update(['correosForecast' => $value]);
        } else {
            DB::connection(self::CONNECTION)
                ->table(self::CLIENT_EXT_TABLE)
                ->insert(['idCliente' => $clienteId, 'correosForecast' => $value]);
        }
    }

    public function paginated(?int $perPage, string $search)
    {
        [$clientColumns, $clientExtColumns] = $this->getClientColumns();

        $canReadClients = in_array('idCliente', $clientColumns, true);

        if (!$canReadClients) {
            return null;
        }

        $selectColumns = [];

        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        foreach ($clientExtColumns as $column) {
            if ($column === 'idCliente') {
                continue;
            }

            $selectColumns[] = 'cle.' . $column . ' as client_ext_' . $column;
        }

        $query = DB::connection(self::CONNECTION)->table(self::CLIENT_TABLE . ' as cl')
            ->when($this->canJoinClientExt($clientExtColumns), function ($query) {
                $query->leftJoin(self::CLIENT_EXT_TABLE . ' as cle', 'cle.idCliente', '=', 'cl.idCliente');
            })
            ->when($search !== '', function ($query) use ($search, $clientColumns) {
                $query->where(function ($subQuery) use ($search, $clientColumns) {
                    if (in_array('razonSocial', $clientColumns, true)) {
                        $subQuery->orWhere('cl.razonSocial', 'like', "%{$search}%");
                    }

                    if (in_array('rfc', $clientColumns, true)) {
                        $subQuery->orWhere('cl.rfc', 'like', "%{$search}%");
                    }

                    if (in_array('email', $clientColumns, true)) {
                        $subQuery->orWhere('cl.email', 'like', "%{$search}%");
                    }

                    if (in_array('idCliente', $clientColumns, true)) {
                        $subQuery->orWhere('cl.idCliente', 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy('cl.idCliente')
            ->select($selectColumns);

        $customers = $query->paginate($perPage ?? 15);

        $customers->through(function ($row) use ($clientColumns, $clientExtColumns) {
            return $this->mapClientRow($row, $clientColumns, $clientExtColumns);
        });

        return $customers;
    }

    public function findById(int $id): ?array
    {
        [$clientColumns, $clientExtColumns] = $this->getClientColumns();

        $hasIdClienteColumn = in_array('idCliente', $clientColumns, true);

        if (!$hasIdClienteColumn) {
            return null;
        }

        $selectColumns = [];

        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        foreach ($clientExtColumns as $column) {
            if ($column === 'idCliente') {
                continue;
            }

            $selectColumns[] = 'cle.' . $column . ' as client_ext_' . $column;
        }

        $row = DB::connection(self::CONNECTION)->table(self::CLIENT_TABLE . ' as cl')
            ->when($this->canJoinClientExt($clientExtColumns), function ($query) {
                $query->leftJoin(self::CLIENT_EXT_TABLE . ' as cle', 'cle.idCliente', '=', 'cl.idCliente');
            })
            ->where('cl.idCliente', $id)
            ->select($selectColumns)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->mapClientRow($row, $clientColumns, $clientExtColumns);
    }

    public function searchByName(string $searchTerm): array
    {
        [$clientColumns, $clientExtColumns] = $this->getClientColumns();

        $hasIdClienteColumn = in_array('idCliente', $clientColumns, true);
        $hasRazonSocialColumn = in_array('razonSocial', $clientColumns, true);

        if (!$hasIdClienteColumn) {
            return [
                'search' => $searchTerm,
                'count' => 0,
                'customers' => [],
            ];
        }

        $selectColumns = [];

        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        foreach ($clientExtColumns as $column) {
            if ($column === 'idCliente') {
                continue;
            }

            $selectColumns[] = 'cle.' . $column . ' as client_ext_' . $column;
        }

        $customers = DB::connection(self::CONNECTION)->table(self::CLIENT_TABLE . ' as cl')
            ->when($this->canJoinClientExt($clientExtColumns), function ($query) {
                $query->leftJoin(self::CLIENT_EXT_TABLE . ' as cle', 'cle.idCliente', '=', 'cl.idCliente');
            })
            ->where(function ($query) use ($searchTerm, $hasRazonSocialColumn, $hasIdClienteColumn) {
                if ($hasRazonSocialColumn) {
                    $query->where('cl.razonSocial', 'LIKE', '%' . $searchTerm . '%');
                }

                if ($hasIdClienteColumn) {
                    $query->orWhere('cl.idCliente', 'LIKE', '%' . $searchTerm . '%');
                }
            })
            ->orderBy('cl.idCliente')
            ->select($selectColumns)
            ->get()
            ->map(function ($row) use ($clientColumns, $clientExtColumns) {
                return $this->mapClientRow($row, $clientColumns, $clientExtColumns);
            })
            ->values();

        return [
            'search' => $searchTerm,
            'count' => $customers->count(),
            'customers' => $customers,
        ];
    }

    private function getClientColumns(): array
    {
        $schema = Schema::connection(self::CONNECTION);

        $clientColumns = $schema->hasTable(self::CLIENT_TABLE)
            ? $schema->getColumnListing(self::CLIENT_TABLE)
            : [];

        $clientExtColumns = $schema->hasTable(self::CLIENT_EXT_TABLE)
            ? $schema->getColumnListing(self::CLIENT_EXT_TABLE)
            : [];

        if (!$this->canJoinClientExt($clientExtColumns)) {
            $clientExtColumns = [];
        }

        return [$clientColumns, $clientExtColumns];
    }

    private function canJoinClientExt(array $clientExtColumns): bool
    {
        return in_array('idCliente', $clientExtColumns, true);
    }

    private function mapClientRow(object $row, array $clientColumns, array $clientExtColumns): array
    {
        $clientData = [];

        foreach ($clientColumns as $column) {
            $clientData[$column] = $row->{'client_' . $column} ?? null;
        }

        $clientExtData = [];
        foreach ($clientExtColumns as $column) {
            if ($column === 'idCliente') {
                continue;
            }

            $clientExtData[$column] = $row->{'client_ext_' . $column} ?? null;
        }

        $managerIds = [];
        foreach (self::MANAGER_COLUMNS as $col) {
            $id = $clientExtData[$col] ?? null;
            if ($id !== null) {
                $managerIds[] = (int) $id;
            }
        }

        $usersMap = $this->fetchUsersWithRole(array_unique($managerIds));

        foreach (self::MANAGER_COLUMNS as $col) {
            $id = $clientExtData[$col] ?? null;
            $clientExtData[$col] = $id !== null ? ($usersMap[(int) $id] ?? null) : null;
        }

        return array_merge($clientData, ['clienteExt' => $clientExtData]);
    }

    private function fetchUsersWithRole(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $uncached = array_values(array_filter($ids, fn($id) => !isset($this->userCache[$id])));

        if (!empty($uncached)) {
            $users = User::with('role')->whereIn('id', $uncached)->get();
            foreach ($users as $user) {
                $this->userCache[(int) $user->id] = [
                    'id'       => (int) $user->id,
                    'fullName' => $user->fullName,
                    'role'     => $user->role ? ['roleName' => $user->role->roleName] : null,
                ];
            }
            foreach ($uncached as $id) {
                $this->userCache[$id] ??= null;
            }
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->userCache[$id] ?? null;
        }

        return $result;
    }
}
