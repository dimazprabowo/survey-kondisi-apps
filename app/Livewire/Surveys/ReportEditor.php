<?php

namespace App\Livewire\Surveys;

use App\Enums\FileStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Survey;
use App\Models\SurveyGroupNote;
use App\Models\SurveyReport;
use App\Models\SurveyReportDocumentation;
use App\Models\SurveyResponse;
use App\Services\SurveyReportService;
use App\Services\SurveyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReportEditor extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public Survey $survey;

    public SurveyReport $report;

    // Meta laporan
    public $report_number;

    public $report_title;

    public $contract_agreement_no;

    public $contract_agreement_date;

    public $contract_appointment_no;

    public $contract_appointment_date;

    public $approval_place;

    public $approval_date;

    public $approver_name;

    public $inspector_1;

    public $inspector_2;

    /**
     * Konten narasi per section: key => string.
     * Key tetap: executive_summary, general, memoranda.
     * Key dinamis: finding_{catId}, saran_{catId}.
     */
    public array $sectionContent = [];

    /**
     * Kategori dari snapshot struktur survey — untuk daftar
     * section finding dan saran per kategori di form.
     */
    public array $categories = [];

    public $documentationPhoto;

    public ?int $documentationCategoryId = null;

    // Kategori BAB III yang sedang dilihat di preview (disinkron dari Alpine, deferred)
    public $previewCategoryId = null;

    public array $cropData = [];

    public bool $showCropModal = false;

    public ?int $deleteDocumentationId = null;

    public bool $showDeleteDocumentationModal = false;

    // Per-request cache responses (dipakai ulang oleh avg calculators)
    protected ?Collection $responsesCache = null;

    public function mount(Survey $survey, SurveyReportService $service, SurveyService $surveyService)
    {
        $survey->loadMissing('ship.certificates');
        $this->survey = $survey;
        $this->report = $service->getOrCreate($survey);
        $this->authorize('view', $this->report);

        $this->fill($this->report->only([
            'report_number', 'report_title',
            'contract_agreement_no', 'contract_appointment_no',
            'approval_place', 'approver_name', 'inspector_1', 'inspector_2',
        ]));
        $this->contract_agreement_date = $this->report->contract_agreement_date?->format('Y-m-d');
        $this->contract_appointment_date = $this->report->contract_appointment_date?->format('Y-m-d');
        $this->approval_date = $this->report->approval_date?->format('Y-m-d');

        $this->sectionContent = $this->report->sections
            ->mapWithKeys(fn ($s) => [$s->key => $s->content ?? ''])
            ->all();

        $this->categories = $surveyService->hydrateStructure($survey->structure)
            ->map(fn ($cat) => ['id' => $cat->id, 'label' => $cat->label])
            ->all();
    }

    public function rules()
    {
        return [
            'report_number' => ['required', 'string', 'max:255'],
            'report_title' => ['required', 'string', 'max:255'],
            'contract_agreement_no' => ['nullable', 'string', 'max:255'],
            'contract_agreement_date' => ['nullable', 'date'],
            'contract_appointment_no' => ['nullable', 'string', 'max:255'],
            'contract_appointment_date' => ['nullable', 'date'],
            'approval_place' => ['nullable', 'string', 'max:255'],
            'approval_date' => ['nullable', 'date'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'inspector_1' => ['nullable', 'string', 'max:255'],
            'inspector_2' => ['nullable', 'string', 'max:255'],
            'sectionContent' => ['array'],
            'sectionContent.*' => ['nullable', 'string', 'max:65000'],
            'documentationPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'cropData.x' => ['nullable', 'numeric', 'min:0'],
            'cropData.y' => ['nullable', 'numeric', 'min:0'],
            'cropData.width' => ['nullable', 'numeric', 'min:1'],
            'cropData.height' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    public function validationAttributes()
    {
        $attrs = [
            'report_number' => 'nomor laporan',
            'report_title' => 'judul laporan',
            'contract_agreement_no' => 'nomor surat perjanjian',
            'contract_agreement_date' => 'tanggal surat perjanjian',
            'contract_appointment_no' => 'nomor surat penunjukan',
            'contract_appointment_date' => 'tanggal surat penunjukan',
            'approval_place' => 'tempat pengesahan',
            'approval_date' => 'tanggal pengesahan',
            'approver_name' => 'nama penyetuju',
            'inspector_1' => 'inspector 1',
            'inspector_2' => 'inspector 2',
            'sectionContent.executive_summary' => 'executive summary',
            'sectionContent.general' => 'narasi BAB I',
            'sectionContent.memoranda' => 'memoranda',
        ];

        foreach ($this->categories as $cat) {
            $attrs['sectionContent.finding_'.$cat['id']] = 'keterangan temuan '.$cat['label'];
            $attrs['sectionContent.saran_'.$cat['id']] = 'saran '.$cat['label'];
        }

        return $attrs;
    }

    public function save(SurveyReportService $service)
    {
        try {
            $this->authorize('update', $this->report);
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah laporan.');

            return;
        }

        try {
            $service->updateMeta($this->report, [
                'report_number' => $this->report_number,
                'report_title' => $this->report_title,
                'contract_agreement_no' => $this->contract_agreement_no ?: null,
                'contract_agreement_date' => $this->contract_agreement_date ?: null,
                'contract_appointment_no' => $this->contract_appointment_no ?: null,
                'contract_appointment_date' => $this->contract_appointment_date ?: null,
                'approval_place' => $this->approval_place ?: null,
                'approval_date' => $this->approval_date ?: null,
                'approver_name' => $this->approver_name ?: null,
                'inspector_1' => $this->inspector_1 ?: null,
                'inspector_2' => $this->inspector_2 ?: null,
            ]);
            $service->saveSections($this->report, $this->sectionContent);
            $this->report->refresh();
            $this->notifySuccess('Laporan berhasil disimpan!');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function generate(SurveyReportService $service)
    {
        try {
            $this->authorize('generate', $this->report);
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk generate laporan.');

            return;
        }

        try {
            // Simpan dulu agar file hasil generate memakai konten terakhir
            $this->save($service);

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $service->requestGenerate($this->report->refresh());
            $this->notifySuccess('Generate laporan dimulai. File akan tersedia setelah proses selesai.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function download(SurveyReportService $service)
    {
        try {
            $this->authorize('view', $this->report);

            if (! $this->report->file_path || $this->report->file_status !== FileStatus::Completed) {
                $this->notifyWarning('File laporan belum tersedia.');

                return null;
            }

            return $service->download($this->report);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengunduh laporan.');

            return null;
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');

            return null;
        }
    }

    /**
     * Polling ringan saat status processing agar UI update tanpa refresh manual.
     */
    public function refreshStatus(): void
    {
        if ($this->report->file_status === FileStatus::Processing) {
            $this->report->refresh();
        }

        $this->report->unsetRelation('documentations');
    }

    public function editSurvey()
    {
        $this->authorize('update', $this->survey);

        $url = route('surveys.edit', $this->survey);
        if ($this->previewCategoryId) {
            $url .= '?category='.urlencode(Crypt::encryptString((string) $this->previewCategoryId));
        }

        return $this->redirect($url, navigate: true);
    }

    public function updatedDocumentationPhoto(): void
    {
        try {
            $this->validateOnly('documentationPhoto');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('documentation-photo-invalid');
            throw $e;
        }
        $this->showCropModal = true;
        $this->dispatch('documentation-photo-ready');
    }

    public function cancelDocumentationCrop(): void
    {
        $this->showCropModal = false;
        $this->documentationPhoto = null;
        $this->cropData = [];
    }

    public function saveDocumentation(SurveyReportService $service): void
    {
        try {
            $this->authorize('update', $this->report);
            abort_unless(collect($this->categories)->contains('id', $this->documentationCategoryId), 404);
            $this->validate([
                'documentationPhoto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
                'cropData.x' => ['required', 'numeric', 'min:0'],
                'cropData.y' => ['required', 'numeric', 'min:0'],
                'cropData.width' => ['required', 'numeric', 'min:1'],
                'cropData.height' => ['required', 'numeric', 'min:1'],
            ]);

            $service->requestDocumentationUpload(
                $this->report,
                $this->documentationCategoryId,
                $this->documentationPhoto,
                $this->cropData
            );
            $this->showCropModal = false;
            $this->documentationPhoto = null;
            $this->cropData = [];
            $this->report->unsetRelation('documentations');
            $this->notifySuccess('Foto dokumentasi sedang diproses.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah dokumentasi.');
        } catch (\Exception $e) {
            report($e);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDeleteDocumentation(int $documentationId): void
    {
        $documentation = SurveyReportDocumentation::findOrFail($documentationId);
        abort_unless($documentation->survey_report_id === $this->report->id, 404);
        $this->authorize('update', $this->report);
        $this->deleteDocumentationId = $documentationId;
        $this->showDeleteDocumentationModal = true;
    }

    public function deleteDocumentation(SurveyReportService $service): void
    {
        try {
            $documentation = SurveyReportDocumentation::findOrFail($this->deleteDocumentationId);
            abort_unless($documentation->survey_report_id === $this->report->id, 404);
            $this->authorize('update', $this->report);
            $service->deleteDocumentation($documentation);
            $this->deleteDocumentationId = null;
            $this->showDeleteDocumentationModal = false;
            $this->report->unsetRelation('documentations');
            $this->notifySuccess('Foto dokumentasi berhasil dihapus.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus dokumentasi.');
        } catch (\Exception $e) {
            report($e);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    // -----------------------------------------------------------------
    //  BAB III read-only preview (delegasi avg ke SurveyService —
    //  pola sama dengan SurveyShow, tanpa duplikasi perhitungan)
    // -----------------------------------------------------------------

    public function getResponsesProperty(): Collection
    {
        return $this->responsesCache ??= SurveyResponse::where('survey_id', $this->survey->id)
            ->get()
            ->keyBy('survey_item_id');
    }

    public function itemGroupAvg($itemGroup): ?float
    {
        return app(SurveyService::class)->itemGroupAvg($itemGroup, $this->responses);
    }

    public function subCategoryAvg($subCat): ?float
    {
        return app(SurveyService::class)->subCategoryAvg($subCat, $this->responses);
    }

    public function categoryAvg($cat): ?float
    {
        return app(SurveyService::class)->categoryAvg($cat, $this->responses);
    }

    public function overallAvg(Collection $categories): ?float
    {
        return app(SurveyService::class)->overallAvg($categories, $this->responses);
    }

    public function editShip()
    {
        $this->authorize('update', $this->survey->ship);

        return $this->redirect(route('master-data.ships.edit', $this->survey->ship), navigate: true);
    }

    public function back()
    {
        return $this->redirect(route('surveys.show', $this->survey), navigate: true);
    }

    public function render()
    {
        return view('livewire.surveys.report-editor', [
            'bab3Categories' => app(SurveyService::class)->hydrateStructure($this->survey->structure),
            'responses' => $this->responses,
            'groupNotes' => SurveyGroupNote::where('survey_id', $this->survey->id)
                ->orderBy('order_num')
                ->get()
                ->groupBy('survey_item_group_id'),
            'documentations' => $this->report->documentations->keyBy('survey_category_id'),
            'capReference' => app(SurveyReportService::class)->capReference(),
        ]);
    }
}
