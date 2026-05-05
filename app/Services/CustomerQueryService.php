<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerQueryService
{
    public function paginated(?int $perPage, string $search)
    {
        $clientTable = 'clientes_tme700618rc7';
        $clientExtTable = 'clientes_tme700618rc7_ext';

        $clientColumns = Schema::hasTable($clientTable)
            ? Schema::getColumnListing($clientTable)
            : [];
        $clientExtColumns = Schema::hasTable($clientExtTable)
            ? Schema::getColumnListing($clientExtTable)
            : [];

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

        $query = DB::table($clientTable . ' as cl')
            ->leftJoin($clientExtTable . ' as cle', 'cle.idCliente', '=', 'cl.idCliente')
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
        $clientTable = 'clientes_tme700618rc7';
        $clientExtTable = 'clientes_tme700618rc7_ext';

        $clientColumns = Schema::hasTable($clientTable)
            ? Schema::getColumnListing($clientTable)
            : [];
        $clientExtColumns = Schema::hasTable($clientExtTable)
            ? Schema::getColumnListing($clientExtTable)
            : [];

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

        $row = DB::table($clientTable . ' as cl')
            ->leftJoin($clientExtTable . ' as cle', 'cle.idCliente', '=', 'cl.idCliente')
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
                return $this->mapClientRow($row, $clientColumns, $clientExtColumns);
            })
            ->values();

        return [
            'search' => $searchTerm,
            'count' => $customers->count(),
            'customers' => $customers,
        ];
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

        return array_merge($clientData, ['clienteExt' => $clientExtData]);
    }
}