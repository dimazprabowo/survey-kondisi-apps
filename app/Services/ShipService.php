<?php

namespace App\Services;

use App\Models\Ship;
use App\Traits\HasDynamicLike;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShipService
{
    use HasDynamicLike;

    /**
     * Query kapal terfilter (dipakai list paginated maupun export non-paginated).
     */
    public function filteredQuery(?string $search = null, ?string $statusFilter = null)
    {
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

        return $query->orderBy('name');
    }

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->filteredQuery($search, $statusFilter)->paginate($perPage);
    }

    public function create(array $data, array $certificates = []): Ship
    {
        return DB::transaction(function () use ($data, $certificates) {
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            $ship = Ship::create($data);
            $this->syncCertificates($ship, $certificates);

            return $ship;
        });
    }

    public function update(Ship $ship, array $data, array $certificates = []): Ship
    {
        return DB::transaction(function () use ($ship, $data, $certificates) {
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }
            $ship->update($data);
            $this->syncCertificates($ship, $certificates);

            return $ship;
        });
    }

    /**
     * Sinkronisasi repeater Status Class — hapus lama, insert ulang berurutan.
     */
    protected function syncCertificates(Ship $ship, array $certificates): void
    {
        $ship->certificates()->delete();

        $order = 0;
        foreach ($certificates as $cert) {
            if (empty($cert['certificate_type'])) {
                continue;
            }

            $order++;
            $ship->certificates()->create([
                'certificate_type' => $cert['certificate_type'],
                'last_date' => $cert['last_date'] ?: null,
                'next_1_date' => $cert['next_1_date'] ?: null,
                'next_2_date' => $cert['next_2_date'] ?: null,
                'postpone_date' => $cert['postpone_date'] ?: null,
                'order_num' => $order,
            ]);
        }
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
