<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerQueryService
{
    public function paginated(?int $perPage, string $search)
    {
        $customerTable = (new Customer())->getTable();
        $clientTable = 'clientes_tme700618rc7';

        $clientColumns = Schema::hasTable($clientTable)
            ? Schema::getColumnListing($clientTable)
            : [];

        $canReadClients = in_array('idCliente', $clientColumns, true);

        if (!$canReadClients) {
            return null;
        }

        $selectColumns = [
            'c.idCustomer',
            'c.idClient',
            'c.salesEngineerId',
            'c.salesManagerId',
            'c.financeManagerId',
            'c.marketingManagerId',
            'c.customerServiceManagerId',
            'c.area',
            'se.id as salesEngineer_id',
            'se.fullName as salesEngineer_name',
            'sm.id as salesManager_id',
            'sm.fullName as salesManager_name',
            'fm.id as financeManager_id',
            'fm.fullName as financeManager_name',
            'mm.id as marketingManager_id',
            'mm.fullName as marketingManager_name',
            'csm.id as customerServiceManager_id',
            'csm.fullName as customerServiceManager_name',
        ];

        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        $query = DB::table($clientTable . ' as cl')
            ->leftJoin($customerTable . ' as c', 'c.idClient', '=', 'cl.idCliente')
            ->leftJoin('users as se', 'c.salesEngineerId', '=', 'se.id')
            ->leftJoin('users as sm', 'c.salesManagerId', '=', 'sm.id')
            ->leftJoin('users as fm', 'c.financeManagerId', '=', 'fm.id')
            ->leftJoin('users as mm', 'c.marketingManagerId', '=', 'mm.id')
            ->leftJoin('users as csm', 'c.customerServiceManagerId', '=', 'csm.id')
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

                    $subQuery->orWhere('se.fullName', 'like', "%{$search}%")
                        ->orWhere('sm.fullName', 'like', "%{$search}%")
                        ->orWhere('fm.fullName', 'like', "%{$search}%")
                        ->orWhere('mm.fullName', 'like', "%{$search}%")
                        ->orWhere('csm.fullName', 'like', "%{$search}%");
                });
            })
            ->orderBy('cl.idCliente')
            ->select($selectColumns);

        $customers = $query->paginate($perPage ?? 15);

        $customers->through(function ($row) use ($clientColumns) {
            $clientData = [];

            foreach ($clientColumns as $column) {
                $clientData[$column] = $row->{'client_' . $column} ?? null;
            }

            $customerData = null;

            if ($row->idCustomer !== null) {
                $customerData = [
                    'idCustomer' => $row->idCustomer,
                    'idClient' => $row->idClient,
                    'area' => $row->area,
                    'salesEngineerId' => $row->salesEngineerId,
                    'salesManagerId' => $row->salesManagerId,
                    'financeManagerId' => $row->financeManagerId,
                    'marketingManagerId' => $row->marketingManagerId,
                    'customerServiceManagerId' => $row->customerServiceManagerId,
                    'salesEngineer' => [
                        'id' => $row->salesEngineer_id,
                        'fullName' => $row->salesEngineer_name,
                    ],
                    'salesManager' => [
                        'id' => $row->salesManager_id,
                        'fullName' => $row->salesManager_name,
                    ],
                    'financeManager' => [
                        'id' => $row->financeManager_id,
                        'fullName' => $row->financeManager_name,
                    ],
                    'marketingManager' => [
                        'id' => $row->marketingManager_id,
                        'fullName' => $row->marketingManager_name,
                    ],
                    'customerServiceManager' => [
                        'id' => $row->customerServiceManager_id,
                        'fullName' => $row->customerServiceManager_name,
                    ],
                ];
            }

            return array_merge($clientData, ['customer' => $customerData]);
        });

        return $customers;
    }

    public function searchByName(string $searchTerm): array
    {
        $clientTable = 'clientes_tme700618rc7';
        $clientExtTable = 'clientes_tme700618rc7_ext';

        $clientColumns = Schema::hasTable($clientTable)
            ? Schema::getColumnListing($clientTable)
            : [];
        $clientExtColumns = Schema::hasTable($clientExtTable)
            ? Schema::getColumnListing($clientExtTable)
            : [];

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

        $customers = DB::table($clientTable . ' as cl')
            ->leftJoin($clientExtTable . ' as cle', 'cle.idCliente', '=', 'cl.idCliente')
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

                return array_merge($clientData, ['clienteExt' => $clientExtData]);
            })
            ->values();

        return [
            'search' => $searchTerm,
            'count' => $customers->count(),
            'customers' => $customers,
        ];
    }
}