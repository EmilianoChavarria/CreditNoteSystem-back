<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserClientService
{
    public function findClientSummaryForUser(User $user): ?array
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));
        $clientId = trim((string) ($user->clientId ?? ''));

        if ($roleName !== 'CUSTOMER' || $clientId === '') {
            return null;
        }

        $clientRow = $this->findClientById($clientId);

        return [
            'razonSocial' => (string) ($clientRow->razonSocial ?? ''),
        ];
    }

    public function isUserAssignedToAnyClient(int $userId): bool
    {
        $columns = [
            'processorId',
            'salesEngineerId',
            'salesManagerId',
            'marketingManagerId',
            'customerServiceManagerId',
            'financeManagerId',
        ];

        foreach (['clientes_tme', 'clientes_TME700618RC7'] as $table) {
            if (!Schema::connection('invoices')->hasTable($table)) {
                continue;
            }

            $query = DB::connection('invoices')->table($table);
            foreach ($columns as $col) {
                $query->orWhere($col, $userId);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function findClientById(string $clientId): ?object
    {
        if (Schema::connection('invoices')->hasTable('clientes_tme')) {
            $row = DB::connection('invoices')->table('clientes_tme')
                ->select('razonSocial')
                ->where('idCliente', $clientId)
                ->first();

            if ($row) {
                return $row;
            }
        }

        if (Schema::connection('invoices')->hasTable('clientes_TME700618RC7')) {
            return DB::connection('invoices')->table('clientes_TME700618RC7')
                ->select('razonSocial')
                ->where('idCliente', $clientId)
                ->first();
        }

        return null;
    }
}
