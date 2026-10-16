<?php

namespace App\Livewire\Surveys;

use App\Enums\FileStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Survey;
use App\Models\SurveyReport;
use App\Services\FileStorageService;
use App\Services\OnlyOfficeService;
use App\Services\SurveyReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ReportWordEditor extends Component
{
    use AuthorizesRequests, HasNotification;

    public Survey $survey;

    public ?SurveyReport $report = null;

    public array $editorConfig = [];

    public string $apiJsUrl = '';

    public ?string $unavailableReason = null;

    public function mount(
        Survey $survey,
        SurveyReportService $reportService,
        OnlyOfficeService $onlyOffice,
        FileStorageService $storage,
    ): void {
        $this->survey = $survey->loadMissing('ship');
        $this->report = $reportService->getOrCreate($survey);
        $this->authorize('editWord', $this->report);

        if (! $onlyOffice->enabled()) {
            $this->unavailableReason = 'Integrasi OnlyOffice belum dikonfigurasi (ONLYOFFICE_URL / ONLYOFFICE_JWT_SECRET).';

            return;
        }

        if (! $this->report->edited_file_path
            && ($this->report->file_status !== FileStatus::Completed || ! $this->report->file_path)) {
            $this->unavailableReason = 'File laporan belum tersedia. Generate DOCX terlebih dahulu.';

            return;
        }

        $source = $onlyOffice->editableSourcePath($this->report);
        if (! $source || ! $storage->exists($source)) {
            $this->unavailableReason = 'File laporan tidak ditemukan di storage. Generate ulang laporan.';

            return;
        }

        $this->apiJsUrl = $onlyOffice->apiJsUrl();
        $this->editorConfig = $onlyOffice->buildEditorConfig($this->report, auth()->user());
    }

    public function download(SurveyReportService $service)
    {
        try {
            $this->authorize('view', $this->report);

            if (! $service->activeFilePath($this->report)) {
                $this->notifyWarning('File laporan belum tersedia.');

                return null;
            }

            return $service->download($this->report);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengunduh laporan.');

            return null;
        }
    }

    public function back()
    {
        return $this->redirect(route('surveys.report', $this->survey), navigate: true);
    }

    public function render()
    {
        return view('livewire.surveys.report-word-editor');
    }
}
