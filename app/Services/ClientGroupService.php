<?php

namespace App\Services;

use App\Models\ClientGroup;
use App\Models\ClientGroupMember;
use App\Models\ForecastComprobante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientGroupService
{
    private const CONNECTION   = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';

    public function __construct(private readonly BanxicoService $banxico) {}

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function all(): Collection
    {
        return ClientGroup::with('members')->get()->map(fn($g) => $this->formatGroup($g));
    }

    public function create(string $name, ?string $description): ClientGroup
    {
        return ClientGroup::create(['name' => $name, 'description' => $description]);
    }

    public function update(int $groupId, string $name, ?string $description): ClientGroup
    {
        $group = ClientGroup::findOrFail($groupId);
        $group->update(['name' => $name, 'description' => $description]);
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
        ClientGroup::findOrFail($groupId);

        $existing = ClientGroupMember::withTrashed()
            ->where('groupId', $groupId)
            ->where('clientId', $clientId)
            ->first();

        $existing ? $existing->restore() : ClientGroupMember::create(['groupId' => $groupId, 'clientId' => $clientId]);
    }

    public function addMembers(int $groupId, array $clientIds): void
    {
        ClientGroup::findOrFail($groupId);

        $records = array_map(fn($cid) => [
            'groupId'   => $groupId,
            'clientId'  => $cid,
            'deletedAt' => null,
        ], $clientIds);

        // deletedAt in update columns ensures soft-deleted members get restored
        ClientGroupMember::upsert($records, ['groupId', 'clientId'], ['deletedAt']);
    }

    public function removeMember(int $groupId, int $clientId): void
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
            'id'          => $group->id,
            'name'        => $group->name,
            'memberCount' => $group->members->count(),            
            'createdAt'   => $group->createdAt
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
