<?php

namespace App\Livewire\Surveys;

use App\Enums\SurveyStatus;
use App\Exports\SurveysExport;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyFolder;
use App\Models\SurveyTemplate;
use App\Services\SurveyFolderService;
use App\Services\SurveyService;
use App\Traits\HasDynamicLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SurveyManagement extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, HasTableSorting, WithPagination;

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

    /** Folder aktif — null berarti root (survey tanpa folder). */
    public ?int $folderId = null;

    /** Folder aktif di query string sebagai ciphertext (persist saat refresh). */
    #[Url(as: 'folder')]
    public ?string $folderKey = null;

    // State modal create/rename folder (dibuka via dispatch open-modal)
    public ?int $editingFolderId = null;

    public string $folderName = '';

    // State modal hapus folder
    public bool $showDeleteFolderModal = false;

    public ?int $deletingFolderId = null;

    public string $deletingFolderName = '';

    // State modal pindah survey ke folder ('' = keluarkan ke root)
    public ?int $movingSurveyId = null;

    /** ID survey untuk bulk move via checkbox — kosong berarti mode single. */
    public array $movingSurveyIds = [];

    public string $movingSurveyNumber = '';

    public string $moveFolderId = '';

    // State modal duplikasi survey ('' = hasil duplikat di root)
    public ?int $duplicatingSurveyId = null;

    public string $duplicatingSurveyNumber = '';

    public string $duplicateFolderId = '';

    public function mount()
    {
        $this->authorize('viewAny', Survey::class);
        $this->sortField = 'survey_date'; // whitelist di SurveyService::getFiltered()
        $this->sortDir = 'desc';
        $this->folderId = $this->resolveFolderKey();
        if ($this->folderKey && ! $this->folderId) {
            $this->folderKey = null; // ciphertext invalid/folder sudah dihapus
        }
    }

    /**
     * Resolve folderId dari ciphertext pada query string.
     * Tampered/expired -> null (fallback ke root).
     */
    private function resolveFolderKey(): ?int
    {
        if (! $this->folderKey) {
            return null;
        }

        try {
            $id = (int) Crypt::decryptString($this->folderKey);
        } catch (\Throwable $e) {
            return null;
        }

        return SurveyFolder::whereKey($id)->exists() ? $id : null;
    }

    // -----------------------------------------------------------------
    //  Foldering
    // -----------------------------------------------------------------

    public function openFolder($id)
    {
        $folder = SurveyFolder::findOrFail($id);
        $this->authorize('view', $folder);
        $this->folderId = $folder->id;
        $this->folderKey = $folder->getRouteKey();
        $this->resetPage();
    }

    public function backToRoot()
    {
        $this->folderId = null;
        $this->folderKey = null;
        $this->resetPage();
    }

    public function createFolder()
    {
        $this->authorize('create', SurveyFolder::class);
        $this->editingFolderId = null;
        $this->folderName = '';
        $this->resetValidation('folderName');
        $this->dispatch('open-modal', 'survey-folder-modal');
    }

    public function editFolder($id)
    {
        $folder = SurveyFolder::findOrFail($id);
        $this->authorize('update', $folder);
        $this->editingFolderId = $folder->id;
        $this->folderName = $folder->name;
        $this->resetValidation('folderName');
        $this->dispatch('open-modal', 'survey-folder-modal');
    }

    public function saveFolder(SurveyFolderService $service)
    {
        try {
            $this->validate([
                'folderName' => [
                    'required', 'string', 'max:255',
                    'unique:survey_folders,name'.($this->editingFolderId ? ','.$this->editingFolderId : ''),
                ],
            ], [], ['folderName' => 'nama folder']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);

            throw $e;
        }

        try {
            if ($this->editingFolderId) {
                $folder = SurveyFolder::findOrFail($this->editingFolderId);
                $this->authorize('update', $folder);
                $service->update($folder, ['name' => $this->folderName]);
                $this->notifySuccess('Folder berhasil diubah!');
            } else {
                $this->authorize('create', SurveyFolder::class);
                $service->create(['name' => $this->folderName]);
                $this->notifySuccess('Folder berhasil dibuat!');
            }
            $this->dispatch('close-modal', 'survey-folder-modal');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengelola folder.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeFolderModal()
    {
        $this->editingFolderId = null;
        $this->folderName = '';
        $this->resetValidation('folderName');
        $this->dispatch('close-modal', 'survey-folder-modal');
    }

    public function confirmDeleteFolder($id)
    {
        $folder = SurveyFolder::findOrFail($id);
        $this->authorize('delete', $folder);
        $this->deletingFolderId = $folder->id;
        $this->deletingFolderName = $folder->name;
        $this->showDeleteFolderModal = true;
    }

    public function deleteFolder(SurveyFolderService $service)
    {
        try {
            $folder = SurveyFolder::findOrFail($this->deletingFolderId);
            $this->authorize('delete', $folder);
            $service->delete($folder);
            $this->notifySuccess('Folder berhasil dihapus!');
            $this->showDeleteFolderModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus folder ini.');
        } catch (\RuntimeException $e) {
            $this->notifyError($e->getMessage());
            $this->showDeleteFolderModal = false;
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function openMoveModal($surveyId)
    {
        $survey = Survey::findOrFail($surveyId);
        $this->authorize('update', $survey);
        $this->movingSurveyId = $survey->id;
        $this->movingSurveyIds = [];
        $this->movingSurveyNumber = $survey->survey_number;
        $this->moveFolderId = $survey->survey_folder_id ? (string) $survey->survey_folder_id : '';
        $this->dispatch('open-modal', 'survey-move-modal');
    }

    /** Buka modal pindah untuk SEMUA survey yang dicentang. */
    public function openBulkMoveModal()
    {
        abort_if($this->selected === [], 404);

        try {
            Survey::whereIn('id', array_map('intval', $this->selected))->get()
                ->each(fn (Survey $s) => $this->authorize('update', $s));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Ada survey yang tidak dapat Anda pindahkan (bukan pemilik).');

            return;
        }

        $this->movingSurveyId = null;
        $this->movingSurveyIds = array_map('intval', $this->selected);
        $this->movingSurveyNumber = '';
        $this->moveFolderId = '';
        $this->dispatch('open-modal', 'survey-move-modal');
    }

    public function saveMove(SurveyFolderService $service)
    {
        try {
            $folder = $this->moveFolderId !== ''
                ? SurveyFolder::findOrFail((int) $this->moveFolderId)
                : null;

            // Mode bulk: pindahkan semua survey tercentang
            if ($this->movingSurveyIds !== []) {
                $surveys = Survey::whereIn('id', $this->movingSurveyIds)->get();
                abort_if($surveys->isEmpty(), 404);
                $surveys->each(fn (Survey $s) => $this->authorize('update', $s));

                $count = $service->moveSurveys($surveys->modelKeys(), $folder);
                $this->selected = [];
                $this->dispatch('close-modal', 'survey-move-modal');
                $this->notifySuccess($folder
                    ? "{$count} survey dipindahkan ke folder {$folder->name}."
                    : "{$count} survey dikeluarkan dari folder.");

                return;
            }

            $survey = Survey::findOrFail($this->movingSurveyId);
            $this->authorize('update', $survey);

            $service->moveSurvey($survey, $folder);
            $this->dispatch('close-modal', 'survey-move-modal');
            $this->notifySuccess($folder
                ? "Survey {$survey->survey_number} dipindahkan ke folder {$folder->name}."
                : "Survey {$survey->survey_number} dikeluarkan dari folder.");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat memindahkan survey ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeMoveModal()
    {
        $this->movingSurveyId = null;
        $this->movingSurveyIds = [];
        $this->movingSurveyNumber = '';
        $this->moveFolderId = '';
        $this->dispatch('close-modal', 'survey-move-modal');
    }

    // -----------------------------------------------------------------
    //  Duplikasi survey (tanpa value respon)
    // -----------------------------------------------------------------

    public function openDuplicateModal($surveyId)
    {
        $survey = Survey::findOrFail($surveyId);
        $this->authorize('view', $survey);
        $this->authorize('create', Survey::class);

        $this->duplicatingSurveyId = $survey->id;
        $this->duplicatingSurveyNumber = $survey->survey_number;
        // Default: folder survey sumber — duplikat berada "di sebelah" aslinya
        $this->duplicateFolderId = $survey->survey_folder_id ? (string) $survey->survey_folder_id : '';
        $this->dispatch('open-modal', 'survey-duplicate-modal');
    }

    public function duplicateSurvey(SurveyService $service)
    {
        try {
            $source = Survey::findOrFail($this->duplicatingSurveyId);
            $this->authorize('view', $source);
            $this->authorize('create', Survey::class);
            $folder = $this->duplicateFolderId !== ''
                ? SurveyFolder::findOrFail((int) $this->duplicateFolderId)
                : null;

            $new = $service->duplicateWithoutValues($source, $folder);
            $this->dispatch('close-modal', 'survey-duplicate-modal');
            $this->notifySuccess("Survey {$source->survey_number} diduplikasi menjadi {$new->survey_number}.");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menduplikasi survey ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeDuplicateModal()
    {
        $this->duplicatingSurveyId = null;
        $this->duplicatingSurveyNumber = '';
        $this->duplicateFolderId = '';
        $this->dispatch('close-modal', 'survey-duplicate-modal');
    }

    /**
     * Opsi folder untuk modal pindah survey.
     */
    public function getFolderOptionsProperty(): array
    {
        return SurveyFolder::orderBy('name')->get()->map(fn ($f) => [
            'value' => (string) $f->id,
            'label' => $f->name,
        ])->toArray();
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
        try {
            $survey = Survey::findOrFail($id);
            $this->authorize('delete', $survey);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus survey ini.');

            return;
        }

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
            $this->sortDir,
            $this->folderScope()
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
            array_map('intval', $this->selected),
            $this->folderScope()
        ))->download('survey-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf(SurveyService $service)
    {
        $this->authorize('exportPdf', Survey::class);

        $query = Survey::with(['ship:id,name,code,year_built', 'creator:id,name', 'template:id,name,code', 'folder:id,name']);
        $service->applyFilters($query, $this->search, $this->statusFilter, $this->shipFilter ? (int) $this->shipFilter : null);

        $surveys = $query
            ->when($this->folderScope() === false, fn ($q) => $q->whereNull('survey_folder_id'))
            ->when(is_int($this->folderScope()), fn ($q) => $q->where('survey_folder_id', $this->folderScope()))
            ->when($this->selected !== [], fn ($q) => $q->whereIn('id', array_map('intval', $this->selected)))
            ->latest('survey_date')
            ->get();

        $pdf = Pdf::loadView('exports.surveys-pdf', [
            'surveys' => $surveys,
            'folderName' => $this->folderId ? SurveyFolder::find($this->folderId)?->name : null,
        ]);
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

        // Survey baru dibuat dari dalam folder langsung masuk folder tsb.
        $params = ['template' => $template->getRouteKey()];
        if ($this->folderId) {
            $params['folder'] = Crypt::encryptString((string) $this->folderId);
        }

        return $this->redirect(route('surveys.create', $params), navigate: true);
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

    /**
     * Scope folder untuk query: di dalam folder = folder tsb; di root tanpa
     * search/filter = hanya survey tanpa folder (false); di root dengan
     * search/filter aktif = global lintas folder (null) agar hasil di dalam
     * folder ikut ditemukan.
     */
    protected function folderScope(): int|false|null
    {
        if ($this->folderId !== null) {
            return $this->folderId;
        }

        return ($this->search !== '' || $this->statusFilter !== '' || $this->shipFilter !== '')
            ? null
            : false;
    }

    /**
     * Susun pesan hasil pencarian/filter: jumlah folder yang cocok beserta
     * total survey di dalamnya, lalu jumlah survey di list. Di dalam folder
     * aktif pesannya menyebut nama folder tsb; saat search global di root,
     * nama folder yang memuat hasil ikut disebutkan.
     */
    protected function searchResultMessage(int $surveyTotal, $folders, ?SurveyFolder $currentFolder): string
    {
        if ($currentFolder) {
            return $surveyTotal > 0
                ? "Ditemukan {$surveyTotal} data survey di folder {$currentFolder->name}."
                : "Tidak ada data yang cocok di folder {$currentFolder->name}.";
        }

        $parts = [];
        if ($folders->isNotEmpty()) {
            $inside = $folders->sum('surveys_count');
            $parts[] = "{$folders->count()} folder ({$inside} survey di dalamnya)";
        }
        if ($surveyTotal > 0 || $parts === []) {
            $parts[] = "{$surveyTotal} data survey";
        }

        $message = 'Ditemukan '.implode(' dan ', $parts).'.';

        // Search global: sebutkan folder mana yang memuat hasil.
        $matched = $folders->filter(fn ($f) => ($f->matched_surveys_count ?? 0) > 0);
        if ($matched->isNotEmpty()) {
            $names = $matched->take(3)
                ->map(fn ($f) => "{$f->name} ({$f->matched_surveys_count})")
                ->implode(', ');
            $suffix = $matched->count() > 3 ? ', +'.($matched->count() - 3).' folder lain' : '';
            $message .= " {$matched->sum('matched_surveys_count')} survey di antaranya ada di folder: {$names}{$suffix}.";
        }

        return $message;
    }

    public function render(SurveyService $service, SurveyFolderService $folderService)
    {
        // Folder aktif bisa saja sudah dihapus user lain — fallback ke root.
        $currentFolder = $this->folderId ? SurveyFolder::find($this->folderId) : null;
        if ($this->folderId && ! $currentFolder) {
            $this->folderId = null;
            $this->folderKey = null;
        }

        $surveys = $service->getFiltered(
            $this->search,
            $this->statusFilter,
            $this->shipFilter ? (int) $this->shipFilter : null,
            15,
            $this->sortField,
            $this->sortDir,
            $this->folderScope()
        );

        // Strip folder hanya di root — folder tidak bertingkat (1 level).
        $folders = $this->folderId === null
            ? $folderService->getFolders($this->search, $this->statusFilter, $this->shipFilter ? (int) $this->shipFilter : null)
            : collect();

        if ($this->filterChanged) {
            $this->notifySuccess($this->searchResultMessage($surveys->total(), $folders, $currentFolder));
            $this->filterChanged = false;
        }

        // Checkbox header checked hanya jika SEMUA baris halaman ini tercentang —
        // dihitung ulang tiap render agar uncheck manual ikut tersinkron.
        $pageIds = $surveys->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selectAll = $pageIds !== [] && array_diff($pageIds, $this->selected) === [];

        return view('livewire.surveys.survey-management', [
            'surveys' => $surveys,
            'folders' => $folders,
            'currentFolder' => $currentFolder,
        ]);
    }
}
