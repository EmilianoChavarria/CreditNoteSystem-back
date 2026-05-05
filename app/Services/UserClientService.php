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
        if (Schema::hasTable('clientes_tme')) {
            $row = DB::table('clientes_tme')
                ->select('razonSocial')
                ->where('idCliente', $clientId)
                ->first();

            if ($row) {
                return $row;
            }
        }

        if (Schema::hasTable('clientes_tme700618rc7')) {
            return DB::table('clientes_tme700618rc7')
                ->select('razonSocial')
                ->where('idCliente', $clientId)
                ->first();
        }

        return null;
    }
}
