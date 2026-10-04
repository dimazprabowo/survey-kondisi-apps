<?php

namespace App\Livewire\MasterData;

use App\Enums\ShipStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Services\ShipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;

class ShipForm extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?Ship $ship = null;

    public bool $editMode = false;

    // Data utama
    public $name;

    public $code;

    public $year_built;

    public $imo_number;

    public $ship_type;

    public $flag;

    public $gross_tonnage;

    public $net_tonnage;

    public $owner;

    public $operator;

    public $status = 'active';

    // Ship particulars (dipakai BAB II laporan)
    public $call_sign;

    public $loa;

    public $lpp;

    public $breadth;

    public $depth;

    public $draft;

    public $dwt;

    public $builder;

    public $port_of_registry;

    public $hull_material;

    public $class_name;

    public $class_notations;

    public $main_engine;

    public $main_engine_power;

    public $aux_engine;

    public $aux_engine_power;

    // Repeater Status Class: [{row_key, certificate_type, last_date, next_1_date, next_2_date, postpone_date}]
    public array $certificates = [];

    public function mount(?Ship $ship = null)
    {
        if ($ship && $ship->exists) {
            $this->authorize('update', $ship);
            $this->ship = $ship;
            $this->editMode = true;

            $this->fill($ship->only([
                'name', 'code', 'year_built', 'imo_number', 'ship_type', 'flag',
                'gross_tonnage', 'net_tonnage', 'owner', 'operator',
                'call_sign', 'loa', 'lpp', 'breadth', 'depth', 'draft', 'dwt',
                'builder', 'port_of_registry', 'hull_material',
                'class_name', 'class_notations',
                'main_engine', 'main_engine_power', 'aux_engine', 'aux_engine_power',
            ]));
            $this->status = $ship->status->value;

            $this->certificates = $ship->certificates->map(fn ($cert) => [
                'row_key' => 'cert-'.$cert->id,
                'certificate_type' => $cert->certificate_type,
                'last_date' => $cert->last_date?->format('Y-m-d') ?? '',
                'next_1_date' => $cert->next_1_date?->format('Y-m-d') ?? '',
                'next_2_date' => $cert->next_2_date?->format('Y-m-d') ?? '',
                'postpone_date' => $cert->postpone_date?->format('Y-m-d') ?? '',
            ])->all();
        } else {
            $this->authorize('create', Ship::class);
            $this->status = ShipStatus::Active->value;
            $this->certificates = [$this->emptyCertificate()];
        }
    }

    protected function emptyCertificate(): array
    {
        return [
            'row_key' => (string) Str::uuid(),
            'certificate_type' => '',
            'last_date' => '',
            'next_1_date' => '',
            'next_2_date' => '',
            'postpone_date' => '',
        ];
    }

    public function addCertificate(): void
    {
        $this->certificates[] = $this->emptyCertificate();
    }

    public function removeCertificate($index): void
    {
        unset($this->certificates[$index]);
        $this->certificates = array_values($this->certificates);
    }

    public function rules()
    {
        $uniqueCode = $this->editMode ? 'unique:ships,code,'.$this->ship->id : 'unique:ships,code';

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', $uniqueCode],
            'year_built' => ['nullable', 'integer', 'min:1900', 'max:'.(int) now()->format('Y')],
            'imo_number' => ['nullable', 'string', 'max:20'],
            'ship_type' => ['nullable', 'string', 'max:100'],
            'flag' => ['nullable', 'string', 'max:100'],
            'gross_tonnage' => ['nullable', 'string', 'max:20'],
            'net_tonnage' => ['nullable', 'string', 'max:20'],
            'owner' => ['nullable', 'string', 'max:255'],
            'operator' => ['nullable', 'string', 'max:255'],
            'call_sign' => ['nullable', 'string', 'max:20'],
            'loa' => ['nullable', 'string', 'max:20'],
            'lpp' => ['nullable', 'string', 'max:20'],
            'breadth' => ['nullable', 'string', 'max:20'],
            'depth' => ['nullable', 'string', 'max:20'],
            'draft' => ['nullable', 'string', 'max:20'],
            'dwt' => ['nullable', 'string', 'max:20'],
            'builder' => ['nullable', 'string', 'max:255'],
            'port_of_registry' => ['nullable', 'string', 'max:255'],
            'hull_material' => ['nullable', 'string', 'max:100'],
            'class_name' => ['nullable', 'string', 'max:255'],
            'class_notations' => ['nullable', 'string', 'max:255'],
            'main_engine' => ['nullable', 'string', 'max:255'],
            'main_engine_power' => ['nullable', 'string', 'max:255'],
            'aux_engine' => ['nullable', 'string', 'max:255'],
            'aux_engine_power' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', ShipStatus::values())],
            'certificates' => ['array'],
            'certificates.*.certificate_type' => ['nullable', 'string', 'max:255'],
            'certificates.*.last_date' => ['nullable', 'date'],
            'certificates.*.next_1_date' => ['nullable', 'date'],
            'certificates.*.next_2_date' => ['nullable', 'date'],
            'certificates.*.postpone_date' => ['nullable', 'date'],
        ];
    }

    public function validationAttributes()
    {
        return [
            'name' => 'nama kapal',
            'code' => 'kode kapal',
            'year_built' => 'tahun pembuatan',
            'imo_number' => 'nomor IMO',
            'ship_type' => 'jenis kapal',
            'flag' => 'bendera',
            'gross_tonnage' => 'gross tonnage',
            'net_tonnage' => 'net tonnage',
            'owner' => 'pemilik',
            'operator' => 'operator',
            'call_sign' => 'call sign',
            'loa' => 'LOA',
            'lpp' => 'LPP',
            'breadth' => 'breadth',
            'depth' => 'tinggi (depth)',
            'draft' => 'draft',
            'dwt' => 'DWT',
            'builder' => 'galangan pembangun',
            'port_of_registry' => 'pelabuhan registrasi',
            'hull_material' => 'material lambung',
            'class_name' => 'kelas',
            'class_notations' => 'notasi kelas',
            'main_engine' => 'main engine',
            'main_engine_power' => 'daya main engine',
            'aux_engine' => 'auxiliary engine',
            'aux_engine_power' => 'daya auxiliary engine',
            'status' => 'status',
            'certificates.*.certificate_type' => 'jenis sertifikat',
            'certificates.*.last_date' => 'tanggal terakhir',
            'certificates.*.next_1_date' => 'tanggal next 1',
            'certificates.*.next_2_date' => 'tanggal next 2',
            'certificates.*.postpone_date' => 'tanggal postpone',
        ];
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(ShipStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    public function save(ShipService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'name' => $this->name,
                'code' => $this->code,
                'year_built' => $this->year_built,
                'imo_number' => $this->imo_number,
                'ship_type' => $this->ship_type,
                'flag' => $this->flag,
                'gross_tonnage' => $this->gross_tonnage,
                'net_tonnage' => $this->net_tonnage,
                'owner' => $this->owner,
                'operator' => $this->operator,
                'call_sign' => $this->call_sign,
                'loa' => $this->loa,
                'lpp' => $this->lpp,
                'breadth' => $this->breadth,
                'depth' => $this->depth,
                'draft' => $this->draft,
                'dwt' => $this->dwt,
                'builder' => $this->builder,
                'port_of_registry' => $this->port_of_registry,
                'hull_material' => $this->hull_material,
                'class_name' => $this->class_name,
                'class_notations' => $this->class_notations,
                'main_engine' => $this->main_engine,
                'main_engine_power' => $this->main_engine_power,
                'aux_engine' => $this->aux_engine,
                'aux_engine_power' => $this->aux_engine_power,
                'status' => $this->status,
            ];

            $certificates = collect($this->certificates)
                ->map(fn ($c) => [
                    'certificate_type' => $c['certificate_type'] ?? '',
                    'last_date' => $c['last_date'] ?? null,
                    'next_1_date' => $c['next_1_date'] ?? null,
                    'next_2_date' => $c['next_2_date'] ?? null,
                    'postpone_date' => $c['postpone_date'] ?? null,
                ])
                ->all();

            if ($this->editMode) {
                $this->authorize('update', $this->ship);
                $service->update($this->ship, $data, $certificates);
                $message = 'Kapal berhasil diupdate!';
            } else {
                $this->authorize('create', Ship::class);
                $service->create($data, $certificates);
                $message = 'Kapal berhasil ditambahkan!';
            }

            $this->notifySuccess($message);

            return $this->redirect(route('master-data.ships'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function cancel()
    {
        return $this->redirect(route('master-data.ships'), navigate: true);
    }

    public function render()
    {
        return view('livewire.master-data.ship-form');
    }
}
