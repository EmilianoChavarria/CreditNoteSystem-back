<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DataExportService
{
    /**
     * @return array{filename: string, sheetName: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function build(string $module, array $filters, mixed $authUser): array
    {
        return match ($this->normalizeModule($module)) {
            'users' => $this->users($filters),
            'clients' => $this->clients($filters),
            'my_approvals' => $this->requests($filters, $authUser, onlyMyApprovals: true),
            'requests' => $this->requests($filters, $authUser, onlyMyApprovals: false),
            default => throw ValidationException::withMessages([
                'module' => ['Modulo de exportacion no soportado.'],
            ]),
        };
    }

    private function normalizeModule(string $module): string
    {
        $module = strtolower(trim($module));

        return match ($module) {
            'user' => 'users',
            'client', 'customers', 'customer' => 'clients',
            'my-approvals', 'my_approval', 'pending_me', 'approvals' => 'my_approvals',
            'request', 'request_list', 'request-list', 'requests_list' => 'requests',
            default => $module,
        };
    }

    /**
     * @return array{filename: string, sheetName: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function users(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $roleName = trim((string) ($filters['roleName'] ?? $filters['role_name'] ?? ''));
        $dateFrom = trim((string) ($filters['dateFrom'] ?? $filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['dateTo'] ?? $filters['date_to'] ?? ''));
        $shouldFilterByRole = $roleName !== '' && strtolower($roleName) !== 'all';

        $users = User::query()
            ->with(['role:id,roleName', 'supervisor:id,fullName,email'])
            ->whereHas('role', fn ($query) => $query->where('roleName', '!=', 'SUPERADMIN'))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('createdAt', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('createdAt', '<=', $dateTo))
            ->when($shouldFilterByRole, function ($query) use ($roleName) {
                $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('roleName', $roleName));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('fullName', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('clientId', 'like', "%{$search}%")
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('roleName', 'like', "%{$search}%"))
                        ->orWhereHas('supervisor', function ($supervisorQuery) use ($search) {
                            $supervisorQuery->where('fullName', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('fullName')
            ->get();

        return [
            'filename' => 'users_' . now()->format('Ymd_His') . '.xls',
            'sheetName' => 'Users',
            'headers' => ['Name', 'Email', 'Role', 'Supervisor', 'Language', 'Customer Number'],
            'rows' => $users->map(fn (User $user) => [
                $user->fullName,
                $user->email,
                $user->role?->roleName,
                $user->supervisor?->fullName,
                $user->preferredLanguage,
                $user->clientId,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{filename: string, sheetName: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function clients(array $filters): array
    {
        $clientTable = 'clientes_TME700618RC7';
        $clientExtTable = 'clientes_TME700618RC7_ext';
        $connection = 'invoices';
        $search = trim((string) ($filters['search'] ?? ''));

        if (!Schema::connection($connection)->hasTable($clientTable)) {
            throw ValidationException::withMessages([
                'module' => ['No existe la tabla de clientes en la conexion invoices.'],
            ]);
        }

        $clientColumns = Schema::connection($connection)->getColumnListing($clientTable);
        $clientExtColumns = Schema::connection($connection)->hasTable($clientExtTable)
            ? Schema::connection($connection)->getColumnListing($clientExtTable)
            : [];

        $selectColumns = [];
        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        foreach ($clientExtColumns as $column) {
            if ($column !== 'idCliente') {
                $selectColumns[] = 'cle.' . $column . ' as client_ext_' . $column;
            }
        }

        $query = DB::connection($connection)->table($clientTable . ' as cl')
            ->when(count($clientExtColumns) > 0, function ($query) use ($clientExtTable) {
                $query->leftJoin($clientExtTable . ' as cle', 'cle.idCliente', '=', 'cl.idCliente');
            })
            ->when($search !== '', function ($query) use ($search, $clientColumns) {
                $query->where(function ($subQuery) use ($search, $clientColumns) {
                    foreach (['idCliente', 'razonSocial', 'rfc', 'email'] as $column) {
                        if (in_array($column, $clientColumns, true)) {
                            $subQuery->orWhere('cl.' . $column, 'like', "%{$search}%");
                        }
                    }
                });
            })
            ->orderBy(in_array('idCliente', $clientColumns, true) ? 'cl.idCliente' : 'cl.' . $clientColumns[0])
            ->select($selectColumns);

        $headers = array_merge($clientColumns, array_map(fn (string $column) => 'ext_' . $column, array_values(array_filter(
            $clientExtColumns,
            fn (string $column) => $column !== 'idCliente'
        ))));

        $rows = $query->get()->map(function (object $row) use ($clientColumns, $clientExtColumns) {
            $values = [];

            foreach ($clientColumns as $column) {
                $values[] = $row->{'client_' . $column} ?? null;
            }

            foreach ($clientExtColumns as $column) {
                if ($column !== 'idCliente') {
                    $values[] = $row->{'client_ext_' . $column} ?? null;
                }
            }

            return $values;
        })->values()->all();

        return [
            'filename' => 'clients_' . now()->format('Ymd_His') . '.xls',
            'sheetName' => 'Clients',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{filename: string, sheetName: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function requests(array $filters, mixed $authUser, bool $onlyMyApprovals): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $roleName = trim((string) ($filters['roleName'] ?? $filters['role_name'] ?? ''));
        $requestTypeId = isset($filters['requestTypeId']) && is_numeric($filters['requestTypeId'])
            ? (int) $filters['requestTypeId']
            : null;
        $dateFrom = trim((string) ($filters['dateFrom'] ?? $filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['dateTo'] ?? $filters['date_to'] ?? ''));
        $shouldFilterByRole = $roleName !== '' && strtolower($roleName) !== 'all';
        $isAdmin = str_contains(mb_strtoupper((string) ($authUser->roleName ?? '')), 'ADMIN');

        $query = RequestModel::query()
            ->with([
                'requestType',
                'user.role',
                'reason',
                'classification',
                'workflowCurrentStep.workflowStep',
                'workflowCurrentStep.assignedRole',
                'workflowCurrentStep.assignedUser',
            ])
            ->when($requestTypeId !== null, fn ($query) => $query->where('requestTypeId', $requestTypeId))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('requestDate', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('requestDate', '<=', $dateTo))
            ->when($onlyMyApprovals, function ($query) use ($authUser, $isAdmin, $shouldFilterByRole, $roleName) {
                $query->whereHas('workflowCurrentStep', function ($workflowQuery) use ($authUser, $isAdmin, $shouldFilterByRole, $roleName) {
                    $workflowQuery->where('status', 'pending');

                    if (!$isAdmin) {
                        $workflowQuery->where('assignedUserId', (int) ($authUser->id ?? 0));
                    }

                    if ($shouldFilterByRole) {
                        $workflowQuery->whereHas('assignedRole', fn ($roleQuery) => $roleQuery->where('roleName', $roleName));
                    }
                });
            })
            ->when(!$onlyMyApprovals && $shouldFilterByRole, function ($query) use ($roleName) {
                $query->whereHas('workflowCurrentStep.assignedRole', fn ($roleQuery) => $roleQuery->where('roleName', $roleName));
            });

        if ($search !== '') {
            $this->applyRequestSearchFilter($query, $search);
        }

        $requests = $query->orderBy('id')->get();

        $customerNames = $this->resolveCustomerNames($requests->pluck('customerId')->filter()->unique()->values()->all());

        return [
            'filename' => ($onlyMyApprovals ? 'my_approvals_' : 'requests_') . now()->format('Ymd_His') . '.xls',
            'sheetName' => $onlyMyApprovals ? 'My Approvals' : 'Requests',
            'headers' => [
                'Request Number',
                'Request Type',
                'Customer Number',
                'Customer Name',
                'Creator',
                'Assigned User',
                'Assigned Role',
                'Reason',
                'Classification',
                'Invoice Number',
                'Amount',
                'Total Amount',
                'Currency',
                'Status',
                'Comments',
            ],
            'rows' => $requests->map(fn (RequestModel $request) => [
                $request->requestNumber,
                $request->requestType?->name ?? $request->requestType?->typeName,
                $request->customerId,
                $customerNames[(string) $request->customerId] ?? null,
                $request->user?->fullName,
                $request->workflowCurrentStep?->assignedUser?->fullName,
                $request->workflowCurrentStep?->assignedRole?->roleName,
                $request->reason?->name,
                $request->classification?->name,
                $request->invoiceNumber,
                $request->amount,
                $request->totalAmount,
                $request->currency,
                $request->status,
                $request->comments,
            ])->values()->all(),
        ];
    }

    /**
     * @param array<int, mixed> $customerIds
     * @return array<string, string>
     */
    private function resolveCustomerNames(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        try {
            return DB::connection('invoices')
                ->table('clientes_TME700618RC7')
                ->whereIn('idCliente', $customerIds)
                ->pluck('razonSocial', 'idCliente')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function applyRequestSearchFilter($query, string $search): void
    {
        $query->where(function ($subQuery) use ($search) {
            $subQuery->where('requestNumber', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('customerId', 'like', "%{$search}%")
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('fullName', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('workflowCurrentStep.assignedUser', function ($assignedUserQuery) use ($search) {
                    $assignedUserQuery->where('fullName', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('reason', fn ($reasonQuery) => $reasonQuery->where('name', 'like', "%{$search}%"))
                ->orWhereHas('classification', function ($classificationQuery) use ($search) {
                    $classificationQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
        });
    }
}
