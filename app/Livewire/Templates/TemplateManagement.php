<?php

namespace App\Livewire\Templates;

use App\Exports\SurveyTemplatesExport;
use App\Exports\SurveyTemplateStructureExport;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\SurveyTemplate;
use App\Services\SurveyTemplateService;
use App\Traits\HasDynamicLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class TemplateManagement extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, HasTableSorting, WithFileUploads, WithPagination;

    public $search = '';

    public $statusFilter = '';

    public bool $filterChanged = false;

    public $showDeleteModal = false;

    public $deletingTemplateId;

    public $deletingTemplateName;

    // State modal import struktur (file Excel format flat per-item)
    public $importFile;

    public string $importMode = 'new';

    public string $importTemplateId = '';

    public string $importName = '';

    public string $importCode = '';

    public function mount()
    {
        $this->authorize('viewAny', SurveyTemplate::class);
        $this->sortField = 'created_at';
        $this->sortDir = 'desc';
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

    public function confirmDelete($id)
    {
        $template = SurveyTemplate::findOrFail($id);
        $this->deletingTemplateId = $template->id;
        $this->deletingTemplateName = $template->name;
        $this->showDeleteModal = true;
    }

    public function delete(SurveyTemplateService $service)
    {
        try {
            $template = SurveyTemplate::findOrFail($this->deletingTemplateId);
            $this->authorize('delete', $template);
            $service->delete($template);
            $this->notifySuccess('Template berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus template ini.');
        } catch (\RuntimeException $e) {
            $this->notifyError($e->getMessage());
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function createTemplate()
    {
        $this->authorize('create', SurveyTemplate::class);

        return $this->redirect(route('master-data.survey-templates.create'), navigate: true);
    }

    public function editTemplate($id)
    {
        $template = SurveyTemplate::findOrFail($id);
        $this->authorize('update', $template);

        return $this->redirect(route('master-data.survey-templates.edit', $template), navigate: true);
    }

    public function viewTemplate($id)
    {
        $template = SurveyTemplate::findOrFail($id);
        $this->authorize('view', $template);

        return $this->redirect(route('master-data.survey-templates.show', $template), navigate: true);
    }

    public function duplicateTemplate(SurveyTemplateService $service, $id)
    {
        try {
            $template = SurveyTemplate::findOrFail($id);
            $this->authorize('duplicate', $template);
            $newTemplate = $service->duplicate($template);
            $this->notifySuccess('Template berhasil diduplikasi: '.$newTemplate->name);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menduplikasi template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function toggleStatus(SurveyTemplateService $service, $id)
    {
        try {
            $template = SurveyTemplate::findOrFail($id);
            $this->authorize('toggleStatus', $template);
            $service->toggleStatus($template);
            $this->notifySuccess('Status template berhasil diubah!');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', SurveyTemplate::class);

        return (new SurveyTemplatesExport($this->search, $this->statusFilter))
            ->download('template-survey-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', SurveyTemplate::class);

        $operator = $this->getLikeOperator();
        $templates = SurveyTemplate::withCount(['surveys', 'categories'])
            ->when($this->search, function ($q) use ($operator) {
                $q->where(function ($q) use ($operator) {
                    $q->where('name', $operator, "%{$this->search}%")
                        ->orWhere('code', $operator, "%{$this->search}%")
                        ->orWhere('description', $operator, "%{$this->search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $pdf = Pdf::loadView('exports.survey-templates-pdf', ['templates' => $templates]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'template-survey-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    // -----------------------------------------------------------------
    //  Import/Export struktur template via Excel
    // -----------------------------------------------------------------

    public function openImportModal()
    {
        $this->authorize('import', SurveyTemplate::class);
        $this->resetImportForm();
        $this->dispatch('open-modal', 'template-import-modal');
    }

    public function closeImportModal()
    {
        $this->resetImportForm();
        $this->dispatch('close-modal', 'template-import-modal');
    }

    protected function resetImportForm(): void
    {
        $this->reset(['importFile', 'importTemplateId', 'importName', 'importCode']);
        $this->importMode = 'new';
        $this->resetValidation();
    }

    public function updatedImportMode(): void
    {
        $this->resetValidation(['importTemplateId', 'importName', 'importCode']);
    }

    public function importStructure(SurveyTemplateService $service)
    {
        $this->authorize('import', SurveyTemplate::class);

        $this->validate([
            'importFile' => file_upload_validation_rule('template-import', true),
            'importMode' => 'required|in:new,existing',
            'importTemplateId' => $this->importMode === 'existing' ? 'required|exists:survey_templates,id' : 'nullable',
            'importName' => $this->importMode === 'new' ? 'required|string|max:255' : 'nullable',
            'importCode' => $this->importMode === 'new' ? 'required|string|max:50|unique:survey_templates,code' : 'nullable',
        ], [], [
            'importFile' => 'file Excel',
            'importMode' => 'mode import',
            'importTemplateId' => 'template tujuan',
            'importName' => 'nama template',
            'importCode' => 'kode template',
        ]);

        try {
            if ($this->importMode === 'existing') {
                $target = SurveyTemplate::findOrFail((int) $this->importTemplateId);
                $this->authorize('update', $target);
                $templateData = [];
            } else {
                $this->authorize('create', SurveyTemplate::class);
                $target = null;
                $templateData = ['name' => trim($this->importName), 'code' => trim($this->importCode)];
            }

            $sheets = Excel::toArray(null, $this->importFile->getRealPath());
            $template = $service->importSheets($sheets, $target, $templateData);

            $this->dispatch('close-modal', 'template-import-modal');
            $this->resetImportForm();
            $this->notifySuccess("Struktur berhasil diimport ke template {$template->name}.");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat melakukan import struktur.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Exception $e) {
            report($e);
            $this->notifyError('File tidak dapat diproses. Pastikan format sesuai template.');
        }
    }

    public function downloadImportTemplate()
    {
        $this->authorize('exportExcel', SurveyTemplate::class);

        return (new SurveyTemplateStructureExport)->download('template-struktur-survey.xlsx');
    }

    public function exportStructure($id)
    {
        $template = SurveyTemplate::findOrFail($id);
        $this->authorize('exportExcel', SurveyTemplate::class);

        return (new SurveyTemplateStructureExport($template))
            ->download('struktur-'.$template->code.'.xlsx');
    }

    public function getImportTemplateOptionsProperty(): array
    {
        return SurveyTemplate::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($t) => ['value' => (string) $t->id, 'label' => "{$t->name} ({$t->code})"])
            ->all();
    }

    public function render(SurveyTemplateService $service)
    {
        $templates = $service->getFiltered(
            $this->search,
            $this->statusFilter,
            15,
            $this->sortField,
            $this->sortDir
        );

        if ($this->filterChanged) {
            $this->notifySuccess("Ditemukan {$templates->total()} data template.");
            $this->filterChanged = false;
        }

        return view('livewire.templates.template-management', compact('templates'));
    }
}
