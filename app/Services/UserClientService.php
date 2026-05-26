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
