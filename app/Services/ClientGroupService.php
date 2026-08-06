<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\ClientGroup;
use App\Models\ClientGroupMember;
use App\Models\ForecastComprobante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientGroupService
{
    private const CONNECTION   = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';

    public function __construct(
        private readonly BanxicoService $banxico,
        private readonly NationalCustomerService $nationalCustomers,
    ) {}

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function all(): Collection
    {
        return ClientGroup::with(['members', 'responsible', 'salesManager'])->get()->map(fn($g) => $this->formatGroup($g));
    }

    public function create(string $name, ?string $description, ?int $responsibleUserId = null, ?int $salesManagerId = null, ?string $clientNumber = null, ?float $returnPercentage = null): ClientGroup
    {
        return ClientGroup::create([
            'name' => $name,
            'clientNumber' => $clientNumber,
            'description' => $description,
            'responsibleUserId' => $responsibleUserId,
            'salesManagerId' => $salesManagerId,
            'returnPercentage' => $returnPercentage,
        ]);
    }

    public function update(int $groupId, string $name, ?string $description, ?int $responsibleUserId = null, ?int $salesManagerId = null, ?string $clientNumber = null, ?float $returnPercentage = null): ClientGroup
    {
        $group = ClientGroup::findOrFail($groupId);
        $group->update([
            'name' => $name,
            'clientNumber' => $clientNumber,
            'description' => $description,
            'responsibleUserId' => $responsibleUserId,
            'salesManagerId' => $salesManagerId,
            'returnPercentage' => $returnPercentage,
        ]);
        return $group;
    }

    public function delete(int $groupId): void
    {
        $group = ClientGroup::findOrFail($groupId);
        // Soft-delete members before soft-deleting the group
        ClientGroupMember::where('groupId', $groupId)->delete();
        $group->delete();
    }

    // ── MEMBERS ───────────────────────────────────────────────────────────────

    public function addMember(int $groupId, string $clientId): void
    {
        $group = ClientGroup::findOrFail($groupId);

        if (!in_array($clientId, $this->filterExistingClientIds($group, [$clientId]), true)) {
            return;
        }

        $existing = ClientGroupMember::withTrashed()
            ->where('groupId', $groupId)
            ->where('clientId', $clientId)
            ->first();

        $existing ? $existing->restore() : ClientGroupMember::create(['groupId' => $groupId, 'clientId' => $clientId]);
    }

    public function addMembers(int $groupId, array $clientIds): void
    {
        $group = ClientGroup::findOrFail($groupId);

        $validIds = $this->filterExistingClientIds($group, $clientIds);

        if (empty($validIds)) {
            return;
        }

        $records = array_map(fn($cid) => [
            'groupId'   => $groupId,
            'clientId'  => $cid,
            'deletedAt' => null,
        ], $validIds);

        // deletedAt in update columns ensures soft-deleted members get restored
        ClientGroupMember::upsert($records, ['groupId', 'clientId'], ['deletedAt']);
    }

    /**
     * Filtra $clientIds a solo los que existen en la BD de invoices; los que no existen
     * se registran en storage/logs/client-group-invalid-ids*.log y se descartan.
     *
     * @return array<int, string> clientId existentes
     */
    private function filterExistingClientIds(ClientGroup $group, array $clientIds): array
    {
        // Los ids se mandan como string explícito: si viajan como entero, PDO los liga como
        // PARAM_INT y MySQL compara idCliente (varchar) por coerción numérica, matcheando
        // también subcuentas tipo "182042-23040" contra "182042".
        $clientIdStrings = array_values(array_unique(array_map('strval', $clientIds)));

        // Solo los clientes del padrón de forecast pueden formar parte de un grupo.
        $existingIds = collect($this->nationalCustomers->activeClientIds())
            ->intersect($clientIdStrings)
            ->values()
            ->all();

        $missingIds = array_values(array_diff($clientIdStrings, $existingIds));

        if (!empty($missingIds)) {
            Log::channel('client_group_invalid_ids')->warning('clientId que no participan en forecast, no se agregaron al grupo', [
                'groupId'    => $group->id,
                'groupName'  => $group->name,
                'missingIds' => $missingIds,
            ]);
        }

        return $existingIds;
    }

    public function removeMember(int $groupId, string $clientId): void
    {
        ClientGroupMember::where('groupId', $groupId)->where('clientId', $clientId)->delete();
    }

    public function getMembers(int $groupId): array
    {
        $group     = ClientGroup::with('members')->findOrFail($groupId);
        $clientIds = $group->members->pluck('clientId')->all();

        $names = empty($clientIds) ? [] : $this->fetchClientNames($clientIds);

        $members = $group->members->map(fn($m) => [
            'clientId'   => $m->clientId,
            'razonSocial' => $names[$m->clientId] ?? (string) $m->clientId,
            'rfc'   => $m->rfc,
        ])->values()->all();

        return [
            'group'   => ['id' => $group->id, 'name' => $group->name],
            'members' => $members,
        ];
    }

    // ── FORECAST AGGREGATE ────────────────────────────────────────────────────

    /**
     * Returns group-level monthly totals (USD) + breakdown per child client.
     *
     * Shape:
     * {
     *   group: { id, name, description },
     *   year: 2026,
     *   months: { 1: { total: 123.45, clients: [ { clientId, name, total } ] }, ... }
     * }
     */
    public function getForecastSummary(int $groupId, int $year): array
    {
        $group   = ClientGroup::with('members')->findOrFail($groupId);
        $members = $group->members;

        if ($members->isEmpty()) {
            return $this->emptyResponse($group, $year);
        }

        $clientIds   = $members->pluck('clientId')->all();
        $clientNames = $this->fetchClientNames($clientIds);
        $sales       = $this->fetchSalesByClient($clientIds, $year);

        // Build month map: 1–12
        $months = [];
        foreach (range(1, 12) as $month) {
            $clientRows = [];
            $groupTotal = 0.0;

            foreach ($clientIds as $cid) {
                $total = (float) ($sales[$cid][$month] ?? 0);
                $groupTotal += $total;

                $clientRows[] = [
                    'clientId' => $cid,
                    'name'     => $clientNames[$cid] ?? (string) $cid,
                    'total'    => round($total, 2),
                ];
            }

            $months[$month] = [
                'total'   => round($groupTotal, 2),
                'clients' => $clientRows,
            ];
        }

        return [
            'group'  => ['id' => $group->id, 'name' => $group->name, 'description' => $group->description],
            'year'   => $year,
            'months' => $months,
        ];
    }

    // ── PRIVATE ───────────────────────────────────────────────────────────────

    /** [clientId => total_usd_per_month[month]] */
    private function fetchSalesByClient(array $clientIds, int $year): array
    {
        $fallbackRate = null;
        $receptorIds  = array_map('strval', $clientIds);

        $rows = ForecastComprobante::whereIn('receptorId', $receptorIds)
            ->where('status', 'Emitido')
            ->whereYear('fechaEmision', $year)
            ->selectRaw('receptorId, MONTH(fechaEmision) as month, SUM(total) as total, moneda, MAX(tipoCambio) as tipoCambio')
            ->groupByRaw('receptorId, MONTH(fechaEmision), moneda')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $cid   = (int) $row->receptorId;
            $month = (int) $row->month;

            if ($row->moneda === 'MXN') {
                $rate = $row->tipoCambio
                    ? (float) $row->tipoCambio
                    : ($fallbackRate ??= $this->banxico->getCurrentUsdRate());

                $usd = $rate > 0 ? (float) $row->total / $rate : 0.0;
            } else {
                $usd = (float) $row->total;
            }

            $map[$cid][$month] = ($map[$cid][$month] ?? 0.0) + $usd;
        }

        return $map;
    }

    /** [clientId => razonSocial] fetched from external DB in one query. */
    private function fetchClientNames(array $clientIds): array
    {
        return DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $clientIds)
            ->pluck('razonSocial', 'idCliente')
            ->all();
    }

    private function formatGroup(ClientGroup $group): array
    {
        return [
            'id'                => $group->id,
            'name'              => $group->name,
            'clientNumber'      => $group->clientNumber,
            'description'       => $group->description,
            'memberCount'       => $group->members->count(),
            'responsibleUserId' => $group->responsibleUserId,
            'responsible'       => $group->responsible ? new UserResource($group->responsible) : null,
            'salesManagerId'    => $group->salesManagerId,
            'salesManager'      => $group->salesManager ? new UserResource($group->salesManager) : null,
            'returnPercentage'  => $group->returnPercentage,
            'createdAt'         => $group->createdAt,
        ];
    }

    private function emptyResponse(ClientGroup $group, int $year): array
    {
        $months = [];
        foreach (range(1, 12) as $m) {
            $months[$m] = ['total' => 0.0, 'clients' => []];
        }

        return [
            'group'  => ['id' => $group->id, 'name' => $group->name, 'description' => $group->description],
            'year'   => $year,
            'months' => $months,
        ];
    }
}
