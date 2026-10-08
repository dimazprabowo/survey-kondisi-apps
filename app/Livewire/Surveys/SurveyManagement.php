<?php

namespace App\Livewire\Surveys;

use App\Enums\SurveyStatus;
use App\Exports\SurveysExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Services\SurveyService;
use App\Traits\HasDynamicLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class SurveyManagement extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $shipFilter = '';

    public bool $filterChanged = false;

    public $showDeleteModal = false;

    public $deletingSurveyId;

    public $deletingSurveyNumber;

    public bool $showTemplateModal = false;

    /** ID survey yang dicentang untuk export (persist antar halaman). */
    public array $selected = [];

    /** State checkbox "pilih semua" di header — dihitung ulang di render(). */
    public bool $selectAll = false;

    /** Kolom sort aktif — whitelist di SurveyService::getFiltered(). */
    public string $sortField = 'survey_date';

    public string $sortDir = 'desc';

    public function mount()
    {
        $this->authorize('viewAny', Survey::class);
    }

    /**
     * Toggle sorting kolom: klik kolom sama = balik arah, kolom baru = asc.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
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

    public function updatingShipFilter()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function resetFilters()
    {
        $this->statusFilter = '';
        $this->shipFilter = '';
        $this->resetPage();
        $this->filterChanged = true;
        $this->notifySuccess('Filter berhasil direset.');
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(SurveyStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    public function getShipOptionsProperty(): array
    {
        return Ship::active()->orderBy('name')->get()->map(fn ($ship) => [
            'value' => (string) $ship->id,
            'label' => $ship->name,
        ])->toArray();
    }

    public function confirmDelete($id)
    {
        $survey = Survey::findOrFail($id);
        $this->deletingSurveyId = $survey->id;
        $this->deletingSurveyNumber = $survey->survey_number;
        $this->showDeleteModal = true;
    }

    public function delete(SurveyService $service)
    {
        try {
            $survey = Survey::findOrFail($this->deletingSurveyId);
            $this->authorize('delete', $survey);
            $service->delete($survey);
            $this->notifySuccess('Survey berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus survey ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    /**
     * Centang/hapus centang semua baris di halaman saat ini.
     */
    public function updatedSelectAll(bool $value)
    {
        $pageIds = app(SurveyService::class)->getFiltered(
            $this->search,
            $this->statusFilter,
            $this->shipFilter ? (int) $this->shipFilter : null,
            15,
            $this->sortField,
            $this->sortDir
        )->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = $value
            ? array_values(array_unique(array_merge($this->selected, $pageIds)))
            : array_values(array_diff($this->selected, $pageIds));
    }

    public function clearSelection()
    {
        $this->selected = [];
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Survey::class);

        return (new SurveysExport(
            $this->search,
            $this->statusFilter,
            $this->shipFilter ? (int) $this->shipFilter : null,
            array_map('intval', $this->selected)
        ))->download('survey-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', Survey::class);

        $operator = $this->getLikeOperator();
        $surveys = Survey::with(['ship:id,name,code,year_built', 'creator:id,name', 'template:id,name,code'])
            ->when($this->search, function ($q) use ($operator) {
                $q->where(function ($q) use ($operator) {
                    $q->where('survey_number', $operator, "%{$this->search}%")
                        ->orWhere('surveyor', $operator, "%{$this->search}%")
                        ->orWhere('location', $operator, "%{$this->search}%")
                        ->orWhereHas('ship', function ($sq) use ($operator) {
                            $sq->where('name', $operator, "%{$this->search}%");
                        });
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->shipFilter, fn ($q) => $q->where('ship_id', (int) $this->shipFilter))
            ->when($this->selected !== [], fn ($q) => $q->whereIn('id', array_map('intval', $this->selected)))
            ->latest('survey_date')
            ->get();

        $pdf = Pdf::loadView('exports.surveys-pdf', ['surveys' => $surveys]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'survey-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function createSurvey()
    {
        $this->authorize('create', Survey::class);
        $this->showTemplateModal = true;
    }

    public function closeTemplateModal()
    {
        $this->showTemplateModal = false;
    }

    public function proceedWithTemplate($templateId)
    {
        $this->authorize('create', Survey::class);
        $template = SurveyTemplate::active()->findOrFail($templateId);

        return $this->redirect(route('surveys.create', ['template' => $template->getRouteKey()]), navigate: true);
    }

    public function getTemplateOptionsProperty(): array
    {
        return SurveyTemplate::active()
            ->withCount('categories')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'description' => $t->description,
                'is_default' => $t->is_default,
                'categories_count' => $t->categories_count,
            ])
            ->toArray();
    }

    public function viewSurvey($id)
    {
        $survey = Survey::findOrFail($id);
        $this->authorize('view', $survey);

        return $this->redirect(route('surveys.show', $survey), navigate: true);
    }

    public function editSurvey($id)
    {
        $survey = Survey::findOrFail($id);
        $this->authorize('update', $survey);

        return $this->redirect(route('surveys.edit', $survey), navigate: true);
    }

    public function render(SurveyService $service)
    {
        $surveys = $service->getFiltered(
            $this->search,
            $this->statusFilter,
            $this->shipFilter ? (int) $this->shipFilter : null,
            15,
            $this->sortField,
            $this->sortDir
        );

        // Checkbox header checked hanya jika SEMUA baris halaman ini tercentang —
        // dihitung ulang tiap render agar uncheck manual ikut tersinkron.
        $pageIds = $surveys->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selectAll = $pageIds !== [] && array_diff($pageIds, $this->selected) === [];

        return view('livewire.surveys.survey-management', compact('surveys'));
    }
}
