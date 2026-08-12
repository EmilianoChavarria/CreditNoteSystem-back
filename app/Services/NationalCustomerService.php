<?php

namespace App\Services;

use App\Models\ClientGroupMember;
use App\Models\ForecastChangeRequest;
use App\Models\ForecastCreditNote;
use App\Models\ForecastSale;
use App\Models\NationalCustomer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Padrón de clientes nacionales que participan en forecast.
 *
 * Solo los registros activos de national_customers son clientes válidos para el
 * módulo: los listados, buscadores y validaciones de forecast se acotan a ellos.
 */
class NationalCustomerService
{
    private const CONNECTION = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';

    /** RFC genérico de extranjero/público general: no aplica para forecast. */
    private const GENERIC_RFC = 'XEXX010101000';

    public function upsertByCustomerNumber(string $customerNumber, array $data): NationalCustomer
    {
        return NationalCustomer::updateOrCreate(
            ['customerNumber' => $customerNumber],
            $data
        );
    }

    public function existsByCustomerNumber(string $customerNumber): bool
    {
        return NationalCustomer::where('customerNumber', $customerNumber)->exists();
    }

    /** Números de cliente que participan hoy en forecast. */
    public function activeClientIds(): array
    {
        return NationalCustomer::pluck('customerNumber')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Moneda en la que participa un cliente del padrón. USD cuando no tiene una
     * asignada: es la moneda por defecto del programa forecast.
     */
    public function currencyFor(string $customerNumber): string
    {
        return $this->currenciesFor([$customerNumber])[$customerNumber] ?? NationalCustomer::CURRENCY_USD;
    }

    /**
     * @param  array<int, string|int> $customerNumbers
     * @return array<string, string> [customerNumber => moneda]
     */
    public function currenciesFor(array $customerNumbers): array
    {
        $numbers = collect($customerNumbers)->map(fn ($n) => (string) $n)->unique()->values();

        if ($numbers->isEmpty()) {
            return [];
        }

        $stored = NationalCustomer::whereIn('customerNumber', $numbers->all())
            ->pluck('currency', 'customerNumber');

        return $numbers
            ->mapWithKeys(fn ($number) => [
                $number => $stored->get($number) ?: NationalCustomer::CURRENCY_USD,
            ])
            ->all();
    }

    /** Valida que el cliente exista en la BD externa con un RFC utilizable. */
    public function existsInInvoices(string $customerNumber): bool
    {
        return $this->validClientsQuery()
            ->whereRaw('TRIM(CAST(idCliente AS CHAR)) = ?', [$customerNumber])
            ->exists();
    }

    /**
     * Listado del padrón: pagina sobre national_customers (el total es el de
     * participantes) y resuelve los datos del cliente en la BD externa solo
     * para la página mostrada.
     */
    public function getPaginated(int $perPage, string $search): LengthAwarePaginator
    {
        $query = NationalCustomer::query();

        if ($search !== '') {
            // razonSocial/rfc viven en la BD externa (otra conexión): se resuelven
            // primero los ids que casan y con esos se filtra el padrón.
            $matchingIds = $this->validClientsQuery()
                ->where(function ($q) use ($search) {
                    $q->where('razonSocial', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%")
                        ->orWhere('idCliente', 'like', "%{$search}%");
                })
                ->pluck('idCliente')
                ->map(fn ($id) => (string) $id)
                ->all();

            $query->where(function ($q) use ($search, $matchingIds) {
                $q->where('customerNumber', 'like', "%{$search}%")
                    ->orWhere('emails', 'like', "%{$search}%");

                if (!empty($matchingIds)) {
                    $q->orWhereIn('customerNumber', $matchingIds);
                }
            });
        }

        $paginator = $query->orderBy('customerNumber')
            ->paginate($perPage)
            ->withQueryString();

        $clients = $this->fetchClients(
            collect($paginator->items())->pluck('customerNumber')->all()
        );

        $paginator->through(function ($customer) use ($clients) {
            $client = $clients->get((string) $customer->customerNumber);

            $customer->razonSocial = $client->razonSocial ?? (string) $customer->customerNumber;
            $customer->rfc         = $client->rfc ?? null;
            $customer->direccion   = $client->direccion ?? null;

            return $customer;
        });

        return $paginator;
    }

    /**
     * Candidatos para el alta: clientes de la BD externa con RFC válido que aún
     * no están en el padrón. Único punto que ve el universo completo de clientes.
     */
    public function searchCandidates(string $term): Collection
    {
        $registered = $this->activeClientIds();

        return $this->validClientsQuery()
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term) {
                $sub->where('razonSocial', 'like', "%{$term}%")
                    ->orWhere('idCliente', 'like', "%{$term}%");
            }))
            ->when(!empty($registered), fn ($q) => $q->whereNotIn('idCliente', $registered))
            ->orderBy('razonSocial')
            ->limit(20)
            ->get(['idCliente', 'razonSocial', 'rfc'])
            ->map(fn ($row) => [
                'customerNumber' => (string) $row->idCliente,
                'razonSocial'    => $row->razonSocial,
                'rfc'            => $row->rfc,
            ]);
    }

    /**
     * Alta de un participante. Si venía dado de baja se restaura con los datos
     * nuevos; su información de forecast anterior permanece borrada.
     */
    public function create(string $customerNumber, array $data = []): NationalCustomer
    {
        $existing = NationalCustomer::withTrashed()
            ->where('customerNumber', $customerNumber)
            ->first();

        if ($existing) {
            $existing->restore();
            $existing->fill($data)->save();

            return $existing;
        }

        return NationalCustomer::create(array_merge($data, ['customerNumber' => $customerNumber]));
    }

    /**
     * Alta masiva por ids (carga del listado inicial).
     *
     * @param  array<int, string> $customerNumbers
     * @return array{agregados: list<string>, restaurados: list<string>, yaExistentes: list<string>, noEncontrados: list<string>}
     */
    public function addMany(array $customerNumbers): array
    {
        $result = ['agregados' => [], 'restaurados' => [], 'yaExistentes' => [], 'noEncontrados' => []];

        $numbers = collect($customerNumbers)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return $result;
        }

        $valid = $this->validClientsQuery()
            ->whereIn('idCliente', $numbers->all())
            ->pluck('idCliente')
            ->map(fn ($id) => (string) $id)
            ->flip();

        $existing = NationalCustomer::withTrashed()
            ->whereIn('customerNumber', $numbers->all())
            ->get()
            ->keyBy(fn ($c) => (string) $c->customerNumber);

        foreach ($numbers as $number) {
            if (!isset($valid[$number])) {
                $result['noEncontrados'][] = $number;

                continue;
            }

            $current = $existing->get($number);

            if ($current === null) {
                NationalCustomer::create(['customerNumber' => $number]);
                $result['agregados'][] = $number;

                continue;
            }

            if ($current->trashed()) {
                $current->restore();
                $result['restaurados'][] = $number;

                continue;
            }

            $result['yaExistentes'][] = $number;
        }

        return $result;
    }

    /**
     * Baja del padrón: borra lógicamente al participante y su información de
     * forecast, que se conserva solo para auditoría. Al reactivarlo arranca en
     * blanco porque esos datos no se restauran.
     */
    public function remove(string $customerNumber): bool
    {
        $customer = NationalCustomer::where('customerNumber', $customerNumber)->first();

        if (!$customer) {
            return false;
        }

        DB::transaction(function () use ($customer, $customerNumber) {
            ForecastSale::where('idClient', $customerNumber)->delete();
            ForecastChangeRequest::where('idClient', $customerNumber)->delete();
            ForecastCreditNote::where('customerNumber', $customerNumber)->delete();
            ClientGroupMember::where('clientId', $customerNumber)->delete();

            $customer->delete();
        });

        return true;
    }

    /**
     * Clientes de la BD externa utilizables en forecast: descarta el RFC genérico
     * de extranjero y las filas fantasma donde idCliente quedó igual al rfc.
     */
    private function validClientsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('rfc', '!=', self::GENERIC_RFC)
            ->whereColumn('idCliente', '!=', 'rfc');
    }

    /** [idCliente => fila] de la BD externa, en una sola consulta. */
    private function fetchClients(array $customerNumbers): Collection
    {
        if (empty($customerNumbers)) {
            return collect();
        }

        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $customerNumbers)
            ->get(['idCliente', 'razonSocial', 'rfc', 'direccion'])
            ->keyBy(fn ($row) => (string) $row->idCliente);
    }
}
