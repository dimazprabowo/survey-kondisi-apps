<?php

namespace App\Livewire\Templates;

use App\Exports\SurveyTemplatesExport;
use App\Livewire\Traits\HasNotification;
use App\Models\SurveyTemplate;
use App\Services\SurveyTemplateService;
use App\Traits\HasDynamicLike;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class TemplateManagement extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    public $search = '';

    public $statusFilter = '';

    public bool $filterChanged = false;

    public $showDeleteModal = false;

    public $deletingTemplateId;

    public $deletingTemplateName;

    public function mount()
    {
        $this->authorize('viewAny', SurveyTemplate::class);
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

    public function render(SurveyTemplateService $service)
    {
        $templates = $service->getFiltered(
            $this->search,
            $this->statusFilter,
            15
        );

        return view('livewire.templates.template-management', compact('templates'));
    }
}
