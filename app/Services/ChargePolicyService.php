<?php

namespace App\Services;

use App\Models\ChargePolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChargePolicyService
{
    public function getAll(): Collection
    {
        return ChargePolicy::orderBy('day')->get();
    }

    public function getById(int $id): ChargePolicy
    {
        return ChargePolicy::findOrFail($id);
    }

    public function create(array $data): ChargePolicy
    {
        return ChargePolicy::create([
            'conditional'=> (string) $data['conditional'],
            'day'        => (int) $data['day'],
            'percentage' => (float) $data['percentage'],
        ]);
    }

    public function update(ChargePolicy $chargePolicy, array $data): ChargePolicy
    {
        $chargePolicy->update([
            'conditional'=> isset($data['conditional']) ? (string) $data['conditional'] : $chargePolicy->conditional,
            'day'        => isset($data['day']) ? (int) $data['day'] : $chargePolicy->day,
            'percentage' => isset($data['percentage']) ? (float) $data['percentage'] : $chargePolicy->percentage,
        ]);

        return $chargePolicy->fresh();
    }

    public function delete(ChargePolicy $chargePolicy): void
    {
        $chargePolicy->delete();
    }

    /**
     * Crea o actualiza un array de políticas en una sola operación.
     * Cada item con 'id' se actualiza; sin 'id' se crea.
     *
     * @param  array<int, array{id?: int|null, conditional: string, day: int, percentage: float}>  $items
     * @return Collection<int, ChargePolicy>
     */
    public function sync(array $items): Collection
    {
        return DB::transaction(function () use ($items) {
            $results = [];

            foreach ($items as $item) {
                if (!empty($item['id'])) {
                    $policy = ChargePolicy::findOrFail((int) $item['id']);
                    $policy->update([
                        'conditional' => (string) $item['conditional'],
                        'day'         => (int) $item['day'],
                        'percentage'  => (float) $item['percentage'],
                    ]);
                    $results[] = $policy->fresh();
                } else {
                    $results[] = ChargePolicy::create([
                        'conditional' => (string) $item['conditional'],
                        'day'         => (int) $item['day'],
                        'percentage'  => (float) $item['percentage'],
                    ]);
                }
            }

            return collect($results);
        });
    }
}
