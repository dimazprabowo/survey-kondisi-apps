<?php

namespace App\Livewire\MasterData;

use App\Enums\ShipStatus;
use App\Exports\ShipsExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Services\ShipService;
use App\Traits\HasDynamicLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class ShipManagement extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    public $search = '';

    public $statusFilter = '';

    public bool $filterChanged = false;

    public $showDeleteModal = false;

    public $deletingShipId;

    public $deletingShipName;

    public function mount()
    {
        $this->authorize('viewAny', Ship::class);
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

    public function create()
    {
        $this->authorize('create', Ship::class);

        return $this->redirect(route('master-data.ships.create'), navigate: true);
    }

    public function edit($id)
    {
        $ship = Ship::findOrFail($id);
        $this->authorize('update', $ship);

        return $this->redirect(route('master-data.ships.edit', $ship), navigate: true);
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

    public function getStatusOptionsProperty(): array
    {
        return collect(ShipStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Ship::class);

        return (new ShipsExport($this->search, $this->statusFilter))
            ->download('kapal-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf(ShipService $service)
    {
        $this->authorize('exportPdf', Ship::class);

        $ships = $service->filteredQuery($this->search, $this->statusFilter)->get();

        $pdf = Pdf::loadView('exports.ships-pdf', ['ships' => $ships]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'kapal-'.now()->format('Y-m-d-His').'.pdf'
        );
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
