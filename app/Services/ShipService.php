<?php

namespace App\Services;

use App\Models\Ship;
use App\Traits\HasDynamicLike;
use Illuminate\Pagination\LengthAwarePaginator;

class ShipService
{
    use HasDynamicLike;

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Ship::withCount('surveys');

        if ($search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, "%{$search}%")
                    ->orWhere('code', $operator, "%{$search}%")
                    ->orWhere('imo_number', $operator, "%{$search}%")
                    ->orWhere('owner', $operator, "%{$search}%")
                    ->orWhere('operator', $operator, "%{$search}%");
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Ship
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return Ship::create($data);
    }

    public function update(Ship $ship, array $data): Ship
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        $ship->update($data);

        return $ship;
    }

    public function delete(Ship $ship): void
    {
        $ship->delete();
    }

    public function toggleStatus(Ship $ship): Ship
    {
        $newStatus = $ship->status->value === 'active' ? 'inactive' : 'active';
        $ship->update(['status' => $newStatus]);

        return $ship;
    }

    public function getOptions(): array
    {
        return Ship::active()->orderBy('name')->pluck('name', 'id')->toArray();
    }
}
