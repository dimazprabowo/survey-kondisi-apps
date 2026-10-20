<?php

namespace App\Services;

use App\Enums\ShipClassStatus;
use App\Models\Ship;
use App\Models\ShipCertificate;
use App\Models\Survey;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShipService
{
    use HasDynamicLike, SortsQuery;

    /**
     * Kolom particulars yang ikut snapshot survey ke master (satu arah balik).
     * `status` (aktif/nonaktif) sengaja dikecualikan — itu flag operasional
     * master, bukan data teknis kapal yang dikoreksi saat survey.
     */
    public const SNAPSHOT_FIELDS = [
        'name', 'year_built', 'imo_number', 'ship_type', 'flag',
        'gross_tonnage', 'net_tonnage', 'owner', 'operator', 'call_sign',
        'loa', 'lpp', 'breadth', 'depth', 'draft', 'dwt',
        'builder', 'port_of_registry', 'hull_material',
        'class_name', 'class_notations', 'class_status',
        'main_engine', 'main_engine_power', 'aux_engine', 'aux_engine_power',
    ];

    /**
     * Label Indonesia per kolom — dipakai tampilan diff sinkron master
     * dan (lowercase) sebagai validation attributes form kapal.
     */
    public static function fieldLabels(): array
    {
        return [
            'name' => 'Nama Kapal',
            'year_built' => 'Tahun Pembuatan',
            'imo_number' => 'Nomor IMO',
            'ship_type' => 'Jenis Kapal',
            'flag' => 'Bendera',
            'gross_tonnage' => 'Gross Tonnage',
            'net_tonnage' => 'Net Tonnage',
            'owner' => 'Pemilik',
            'operator' => 'Operator',
            'call_sign' => 'Call Sign',
            'loa' => 'Length Overall (LOA)',
            'lpp' => 'Length Between Perpendiculars (LPP)',
            'breadth' => 'Breadth (B)',
            'depth' => 'Tinggi (Depth)',
            'draft' => 'Draft',
            'dwt' => 'DWT',
            'builder' => 'Galangan Pembangun',
            'port_of_registry' => 'Pelabuhan Registrasi',
            'hull_material' => 'Material Lambung',
            'class_name' => 'Kelas',
            'class_notations' => 'Notasi Kelas',
            'class_status' => 'Status Kelas',
            'main_engine' => 'Main Engine',
            'main_engine_power' => 'Daya Main Engine',
            'aux_engine' => 'Auxiliary Engine',
            'aux_engine_power' => 'Daya Auxiliary Engine',
        ];
    }

    /**
     * Query kapal terfilter (dipakai list paginated maupun export non-paginated).
     */
    public function filteredQuery(?string $search = null, ?string $statusFilter = null, ?string $sortField = null, string $sortDir = 'asc')
    {
        $query = Ship::withCount('surveys');

        if ($search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, "%{$search}%")
                    ->orWhere('imo_number', $operator, "%{$search}%")
                    ->orWhere('owner', $operator, "%{$search}%")
                    ->orWhere('operator', $operator, "%{$search}%");
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $sortable = [
            'name' => 'ships.name',
            'year_built' => 'ships.year_built',
            'ship_type' => 'ships.ship_type',
            'owner' => 'ships.owner',
            'surveys_count' => 'surveys_count',
            'status' => 'ships.status',
        ];

        $this->applySorting($query, $sortable, $sortField, $sortDir, 'name', 'asc', 'ships.id');

        return $query;
    }

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        return $this->filteredQuery($search, $statusFilter, $sortField, $sortDir)->paginate($perPage);
    }

    public function create(array $data, array $certificates = []): Ship
    {
        return DB::transaction(function () use ($data, $certificates) {
            $ship = Ship::create($this->normalizeData($data));
            $this->syncCertificates($ship, $certificates);

            return $ship;
        });
    }

    public function update(Ship $ship, array $data, array $certificates = []): Ship
    {
        return DB::transaction(function () use ($ship, $data, $certificates) {
            $ship->update($this->normalizeData($data));
            $this->syncCertificates($ship, $certificates);

            return $ship;
        });
    }

    /**
     * Normalisasi input: trim string, kosong → null (string '' melanggar
     * constraint unik call_sign di level DB), uppercase identifier unik.
     * Public — dipakai ulang SurveyForm untuk menormalisasi snapshot kapal.
     */
    public function normalizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['imo_number', 'call_sign'] as $identifier) {
            if (is_string($data[$identifier] ?? null)) {
                $data[$identifier] = strtoupper($data[$identifier]);
            }
        }

        return $data;
    }

    /**
     * Normalisasi baris repeater sertifikat: buang baris tanpa jenis,
     * trim tipe, tanggal kosong → null. Dipakai bersama oleh snapshot
     * survey, diff, dan sinkronisasi master.
     */
    public function normalizedCertificates(array $certificates): array
    {
        $normalized = [];

        foreach ($certificates as $cert) {
            $type = is_string($cert['certificate_type'] ?? null)
                ? trim($cert['certificate_type'])
                : '';

            if ($type === '') {
                continue;
            }

            $normalized[] = [
                'certificate_type' => $type,
                'last_date' => $this->dateOrNull($cert['last_date'] ?? null),
                'next_1_date' => $this->dateOrNull($cert['next_1_date'] ?? null),
                'next_2_date' => $this->dateOrNull($cert['next_2_date'] ?? null),
                'postpone_date' => $this->dateOrNull($cert['postpone_date'] ?? null),
            ];
        }

        return $normalized;
    }

    protected function dateOrNull(mixed $value): ?string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Sinkronisasi repeater Status Class — hapus lama, insert ulang berurutan.
     */
    protected function syncCertificates(Ship $ship, array $certificates): void
    {
        $ship->certificates()->delete();

        foreach ($this->normalizedCertificates($certificates) as $order => $cert) {
            $ship->certificates()->create($cert + ['order_num' => $order + 1]);
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

    public function updateClassStatus(Ship $ship, ShipClassStatus $status): Ship
    {
        $ship->update(['class_status' => $status]);

        return $ship;
    }

    public function getOptions(): array
    {
        return Ship::active()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    // ------------------------------------------------------------------
    // Snapshot data kapal pada survey
    // ------------------------------------------------------------------

    /**
     * Bentuk snapshot {attributes, certificates} dari record kapal — dipakai
     * untuk auto-isi form survey saat kapal dipilih.
     */
    public function snapshotFromShip(Ship $ship): array
    {
        $attributes = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $value = $ship->getAttribute($field);
            $attributes[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $ship->loadMissing(['certificates' => fn ($q) => $q->orderBy('order_num')]);

        return [
            'attributes' => $attributes,
            'certificates' => $this->normalizedCertificates($ship->certificates->all()),
        ];
    }

    /**
     * Cari kapal master yang jadi target sinkron snapshot: cocokkan Call Sign
     * (case-insensitive) dulu, fallback ke ship_id yang dipilih di form.
     */
    public function resolveForSnapshot(array $data, ?int $fallbackShipId = null): ?Ship
    {
        $callSign = strtoupper(trim((string) ($data['call_sign'] ?? '')));

        if ($callSign !== '') {
            $ship = Ship::whereRaw('UPPER(call_sign) = ?', [$callSign])->first();
            if ($ship) {
                return $ship;
            }
        }

        return $fallbackShipId ? Ship::find($fallbackShipId) : null;
    }

    /**
     * Hitung perbedaan snapshot terhadap record kapal — untuk preview modal
     * konfirmasi sinkron (label + nilai lama → baru) dan deteksi no-op.
     */
    public function snapshotDiff(Ship $ship, array $data, array $certificates = []): array
    {
        $data = $this->normalizeData(array_intersect_key($data, array_flip(self::SNAPSHOT_FIELDS)));
        $labels = self::fieldLabels();
        $fields = [];

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $old = $ship->getAttribute($field);
            if ($old instanceof \BackedEnum) {
                $old = $old->value;
            }
            $old = is_string($old) && trim($old) === '' ? null : $old;
            $new = $data[$field] ?? null;

            if ((string) ($old ?? '') !== (string) ($new ?? '')) {
                $fields[] = [
                    'key' => $field,
                    'label' => $labels[$field] ?? $field,
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        $oldCerts = $this->normalizedCertificates(
            $ship->certificates()->orderBy('order_num')->get()->all()
        );
        $newCerts = $this->normalizedCertificates($certificates);

        return [
            'fields' => $fields,
            'certificates_changed' => $oldCerts !== $newCerts,
            'old_cert_count' => count($oldCerts),
            'new_cert_count' => count($newCerts),
        ];
    }

    /**
     * Timpa master data kapal dari snapshot survey (dipicu saat status survey
     * menjadi completed dan user mengonfirmasi modal). Semua field snapshot
     * ditimpa termasuk yang kosong — snapshot adalah sumber kebenaran.
     */
    public function applySnapshotFromSurvey(Survey $survey): ?Ship
    {
        $data = $survey->ship_snapshot['attributes'] ?? null;

        if (! is_array($data) || empty($data['name'])) {
            return null;
        }

        // Kolom NOT NULL tanpa default aman — bila kosong di snapshot,
        // pertahankan nilai master agar tidak melanggar constraint.
        foreach (['name', 'class_status'] as $required) {
            if (($data[$required] ?? null) === null) {
                unset($data[$required]);
            }
        }

        $ship = $this->resolveForSnapshot($data, $survey->ship_id);

        if (! $ship) {
            // "Kapal Baru" — survey tanpa link master: buat record kapal dari
            // snapshot lalu tautkan survey ke kapal itu (satu transaksi dengan
            // penyimpanan survey lewat saveWithShipSync).
            if ($survey->ship_id !== null) {
                return null;
            }

            $ship = $this->create($data, $survey->ship_snapshot['certificates'] ?? []);
            $survey->update(['ship_id' => $ship->id]);

            return $ship;
        }

        return $this->update($ship, $data, $survey->ship_snapshot['certificates'] ?? []);
    }

    /**
     * Bangun instance Ship non-persisted dari snapshot — dipakai report
     * builder agar laporan membaca data kapal saat survey diisi (immutable),
     * bukan state master terkini. $fallback dipakai untuk survey lama
     * yang belum punya snapshot.
     */
    public function hydrateFromSnapshot(array $snapshot, ?Ship $fallback = null): ?Ship
    {
        $attributes = $snapshot['attributes'] ?? null;
        $certificates = $snapshot['certificates'] ?? null;

        if ($attributes === null) {
            return $fallback;
        }

        $ship = $fallback ? clone $fallback : new Ship;
        $ship->forceFill(array_intersect_key($attributes, array_flip(self::SNAPSHOT_FIELDS)));
        $ship->setRelation('certificates', collect($certificates ?? [])
            ->map(fn ($cert) => new ShipCertificate(is_array($cert) ? $cert : [])));

        return $ship;
    }
}
