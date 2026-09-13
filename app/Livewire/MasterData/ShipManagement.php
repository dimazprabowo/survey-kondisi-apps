<?php

namespace App\Livewire\MasterData;

use App\Enums\ShipStatus;
use App\Exports\ShipsExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Services\ShipService;
use App\Traits\HasDynamicLike;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class ShipManagement extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    public $search = '';

    public $statusFilter = '';

    public bool $filterChanged = false;

    public $showModal = false;

    public $editMode = false;

    public $shipId;

    public $name;

    public $code;

    public $year_built;

    public $imo_number;

    public $ship_type;

    public $flag;

    public $gross_tonnage;

    public $owner;

    public $operator;

    public $status = 'active';

    public $showDeleteModal = false;

    public $deletingShipId;

    public $deletingShipName;

    public function mount()
    {
        $this->authorize('viewAny', Ship::class);
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', $this->editMode ? 'unique:ships,code,'.$this->shipId : 'unique:ships,code'],
            'year_built' => ['nullable', 'integer', 'min:1900', 'max:'.(int) now()->format('Y')],
            'imo_number' => ['nullable', 'string', 'max:20'],
            'ship_type' => ['nullable', 'string', 'max:100'],
            'flag' => ['nullable', 'string', 'max:100'],
            'gross_tonnage' => ['nullable', 'string', 'max:20'],
            'owner' => ['nullable', 'string', 'max:255'],
            'operator' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', ShipStatus::values())],
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
            'owner' => 'pemilik',
            'operator' => 'operator',
            'status' => 'status',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function resetFilters()
    {
        $this->statusFilter = '';
        $this->resetPage();
        $this->filterChanged = true;
        $this->notifySuccess('Filter berhasil direset.');
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(ShipStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    public function create()
    {
        $this->authorize('create', Ship::class);
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $ship = Ship::findOrFail($id);
        $this->authorize('update', $ship);

        $this->shipId = $ship->id;
        $this->name = $ship->name;
        $this->code = $ship->code;
        $this->year_built = $ship->year_built;
        $this->imo_number = $ship->imo_number;
        $this->ship_type = $ship->ship_type;
        $this->flag = $ship->flag;
        $this->gross_tonnage = $ship->gross_tonnage;
        $this->owner = $ship->owner;
        $this->operator = $ship->operator;
        $this->status = $ship->status->value;

        $this->editMode = true;
        $this->showModal = true;
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
                'owner' => $this->owner,
                'operator' => $this->operator,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $ship = Ship::findOrFail($this->shipId);
                $this->authorize('update', $ship);
                $service->update($ship, $data);
                $message = 'Kapal berhasil diupdate!';
            } else {
                $this->authorize('create', Ship::class);
                $service->create($data);
                $message = 'Kapal berhasil ditambahkan!';
            }

            $this->notifySuccess($message);
            $this->closeModal();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDelete($id)
    {
        $ship = Ship::findOrFail($id);
        $this->deletingShipId = $ship->id;
        $this->deletingShipName = $ship->name;
        $this->showDeleteModal = true;
    }

    public function delete(ShipService $service)
    {
        try {
            $ship = Ship::findOrFail($this->deletingShipId);
            $this->authorize('delete', $ship);

            if ($ship->surveys()->exists()) {
                $this->notifyError('Kapal tidak dapat dihapus karena masih memiliki survey terkait.');
                $this->showDeleteModal = false;

                return;
            }

            $service->delete($ship);
            $this->notifySuccess('Kapal berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus kapal ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function toggleStatus($id, ShipService $service)
    {
        try {
            $ship = Ship::findOrFail($id);
            $this->authorize('toggleStatus', $ship);
            $service->toggleStatus($ship);
            $status = $ship->fresh()->status->label();
            $this->notifySuccess("Status kapal berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status kapal.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm()
    {
        $this->reset([
            'shipId', 'name', 'code', 'year_built', 'imo_number',
            'ship_type', 'flag', 'gross_tonnage', 'owner', 'operator', 'status',
        ]);
        $this->status = ShipStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Ship::class);

        return (new ShipsExport($this->search, $this->statusFilter))
            ->download('kapal-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function render(ShipService $service)
    {
        $ships = $service->getFiltered(
            $this->search,
            $this->statusFilter,
            15
        );

        return view('livewire.master-data.ship-management', compact('ships'));
    }
}
