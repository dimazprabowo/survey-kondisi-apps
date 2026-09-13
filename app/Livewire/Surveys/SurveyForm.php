<?php

namespace App\Livewire\Surveys;

use App\Enums\SurveyStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyTemplate;
use App\Services\SurveyService;
use App\Services\SurveyTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;

class SurveyForm extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?Survey $survey = null;

    public bool $editMode = false;

    // Header fields
    public $surveyId;

    public $ship_id;

    public $survey_template_id;

    public $survey_date;

    public $surveyor;

    public $location;

    public $status = 'draft';

    public $notes;

    // Dynamic responses: [item_id => ['scores' => [label => value], 'note' => '', 'date_issued' => '', 'date_expired' => '']]
    public $responses = [];

    // Active tab (category)
    public $activeCategory = 1;

    // Draft auto-save indicator (for UI feedback)
    public bool $draftSaved = false;

    public function mount(?Survey $survey = null)
    {
        if ($survey && $survey->exists) {
            $this->authorize('update', $survey);
            $this->survey = $survey;
            $this->editMode = true;
            $this->surveyId = $survey->id;
            $this->ship_id = $survey->ship_id;
            $this->survey_template_id = $survey->survey_template_id;
            $this->survey_date = $survey->survey_date?->format('Y-m-d');
            $this->surveyor = $survey->surveyor;
            $this->location = $survey->location;
            $this->status = $survey->status->value;
            $this->notes = $survey->notes;

            // Load existing responses
            $this->loadResponses();
        } else {
            $this->authorize('create', Survey::class);
            $this->survey_date = now()->format('Y-m-d');
            $this->status = SurveyStatus::Draft->value;

            // Template dari query param (dari template picker modal) atau default
            $templateParam = request()->query('template');
            if ($templateParam) {
                try {
                    $templateId = Crypt::decryptString($templateParam);
                    $template = SurveyTemplate::active()->find($templateId);
                    if ($template) {
                        $this->survey_template_id = $template->id;
                    }
                } catch (\Exception $e) {
                    // Invalid template param, fall back to default
                }
            }

            // Fall back to default template if not set
            if (! $this->survey_template_id) {
                $defaultTemplate = (new SurveyTemplateService)->getDefault();
                $this->survey_template_id = $defaultTemplate?->id;
            }

            $this->initEmptyResponses();
            $this->activeCategory = $this->categories->first()?->id ?? 1;

            // Restore draft if exists (auto-recovery after reload)
            $this->loadDraft();
        }
    }

    protected function loadResponses(): void
    {
        $this->responses = [];
        $existing = \App\Models\SurveyResponse::where('survey_id', $this->surveyId)
            ->get()
            ->keyBy('survey_item_id');

        $items = $this->getTemplateItems();
        foreach ($items as $item) {
            $response = $existing->get($item->id);
            $this->responses[$item->id] = [
                'scores' => $response?->scores ?? [],
                'note' => $response?->note ?? '',
                'date_issued' => $response?->date_issued?->format('Y-m-d') ?? '',
                'date_expired' => $response?->date_expired?->format('Y-m-d') ?? '',
            ];
            // Ensure all score labels have keys
            foreach ($item->score_labels as $label) {
                if (! isset($this->responses[$item->id]['scores'][$label])) {
                    $this->responses[$item->id]['scores'][$label] = '';
                }
            }
        }
    }

    protected function initEmptyResponses(): void
    {
        $this->responses = [];
        $items = $this->getTemplateItems();
        foreach ($items as $item) {
            $scores = [];
            foreach ($item->score_labels as $label) {
                $scores[$label] = '';
            }
            $this->responses[$item->id] = [
                'scores' => $scores,
                'note' => '',
                'date_issued' => '',
                'date_expired' => '',
            ];
        }
    }

    /**
     * Get items for the selected template (with hierarchy eager loaded).
     */
    protected function getTemplateItems()
    {
        if (! $this->survey_template_id) {
            return collect();
        }

        return SurveyItem::whereHas('itemGroup.subCategory.category', function ($q) {
            $q->where('survey_template_id', $this->survey_template_id);
        })
            ->with('itemGroup.subCategory.category')
            ->orderBy('order_num')
            ->get();
    }

    public function rules()
    {
        return [
            'ship_id' => ['required', 'exists:ships,id'],
            'survey_template_id' => ['required', 'exists:survey_templates,id'],
            'survey_date' => ['required', 'date'],
            'surveyor' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', SurveyStatus::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function validationAttributes()
    {
        return [
            'ship_id' => 'kapal',
            'survey_template_id' => 'template',
            'survey_date' => 'tanggal survey',
            'surveyor' => 'surveyor',
            'location' => 'lokasi',
            'status' => 'status',
            'notes' => 'catatan',
        ];
    }

    public function getShipOptionsProperty(): array
    {
        return Ship::active()->orderBy('name')->get()->map(fn ($ship) => [
            'value' => (string) $ship->id,
            'label' => $ship->name.($ship->year_built ? ' ('.$ship->year_built.')' : ''),
        ])->toArray();
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(SurveyStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    public function getCategoriesProperty()
    {
        if (! $this->survey_template_id) {
            return collect();
        }

        return SurveyCategory::with(['subCategories.itemGroups.items'])
            ->where('survey_template_id', $this->survey_template_id)
            ->orderBy('order_num')
            ->get();
    }

    /**
     * Calculate item average live (mirrors Excel =AVERAGE(E:F))
     */
    public function calculateItemAvg($itemId): ?float
    {
        $response = $this->responses[$itemId] ?? null;
        if (! $response || empty($response['scores'])) {
            return null;
        }

        $values = array_filter($response['scores'], fn ($v) => is_numeric($v) && $v !== '' && $v !== '-');
        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * Calculate item group average (average of item averages)
     */
    public function calculateItemGroupAvg($itemGroupId): ?float
    {
        $itemIds = SurveyItem::where('survey_item_group_id', $itemGroupId)
            ->whereHas('itemGroup.subCategory.category', fn ($q) => $q->where('survey_template_id', $this->survey_template_id))
            ->pluck('id');
        $avgs = [];
        foreach ($itemIds as $id) {
            $avg = $this->calculateItemAvg($id);
            if ($avg !== null) {
                $avgs[] = $avg;
            }
        }
        if (empty($avgs)) {
            return null;
        }

        return round(array_sum($avgs) / count($avgs), 2);
    }

    /**
     * Calculate sub-category average (average of item group averages)
     */
    public function calculateSubCategoryAvg($subCategoryId): ?float
    {
        $itemGroupIds = \App\Models\SurveyItemGroup::where('survey_sub_category_id', $subCategoryId)
            ->whereHas('subCategory.category', fn ($q) => $q->where('survey_template_id', $this->survey_template_id))
            ->pluck('id');
        $avgs = [];
        foreach ($itemGroupIds as $id) {
            $avg = $this->calculateItemGroupAvg($id);
            if ($avg !== null) {
                $avgs[] = $avg;
            }
        }
        if (empty($avgs)) {
            return null;
        }

        return round(array_sum($avgs) / count($avgs), 2);
    }

    /**
     * Calculate category average (average of sub-category averages)
     */
    public function calculateCategoryAvg($categoryId): ?float
    {
        $subCatIds = \App\Models\SurveySubCategory::where('survey_category_id', $categoryId)
            ->whereHas('category', fn ($q) => $q->where('survey_template_id', $this->survey_template_id))
            ->pluck('id');
        $avgs = [];
        foreach ($subCatIds as $id) {
            $avg = $this->calculateSubCategoryAvg($id);
            if ($avg !== null) {
                $avgs[] = $avg;
            }
        }
        if (empty($avgs)) {
            return null;
        }

        return round(array_sum($avgs) / count($avgs), 2);
    }

    /**
     * Calculate overall CAP score (average of category averages)
     */
    public function calculateOverallAvg(): ?float
    {
        $catIds = SurveyCategory::where('survey_template_id', $this->survey_template_id)->pluck('id');
        $avgs = [];
        foreach ($catIds as $id) {
            $avg = $this->calculateCategoryAvg($id);
            if ($avg !== null) {
                $avgs[] = $avg;
            }
        }
        if (empty($avgs)) {
            return null;
        }

        return round(array_sum($avgs) / count($avgs), 2);
    }

    public function setCategory($categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    public function save(SurveyService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'ship_id' => $this->ship_id,
                'survey_template_id' => $this->survey_template_id,
                'survey_date' => $this->survey_date,
                'surveyor' => $this->surveyor,
                'location' => $this->location,
                'status' => $this->status,
                'notes' => $this->notes,
            ];

            if ($this->editMode) {
                $survey = Survey::findOrFail($this->surveyId);
                $this->authorize('update', $survey);
                $service->update($survey, $data);
                $service->saveResponses($survey->id, $this->responses);
                $this->notifySuccess('Survey berhasil diupdate!');
            } else {
                $this->authorize('create', Survey::class);
                $survey = $service->create($data);
                $service->saveResponses($survey->id, $this->responses);
                $this->clearDraft();
                $this->notifySuccess('Survey berhasil dibuat!');
            }

            return $this->redirect(route('surveys.index'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function cancel()
    {
        // Clear draft when user intentionally leaves the form
        if (! $this->editMode) {
            $this->clearDraft();
        }

        return $this->redirect(route('surveys.index'), navigate: true);
    }

    /**
     * Save draft to Livewire session (called from Alpine auto-save).
     * Only works in create mode (edit mode data is already in DB).
     */
    public function saveDraft()
    {
        if ($this->editMode) {
            return;
        }

        session()->put('survey_draft_'.$this->survey_template_id, [
            'ship_id' => $this->ship_id,
            'survey_date' => $this->survey_date,
            'surveyor' => $this->surveyor,
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'responses' => $this->responses,
            'activeCategory' => $this->activeCategory,
            'saved_at' => now()->toIso8601String(),
        ]);

        $this->draftSaved = true;
    }

    /**
     * Restore draft from session (called in mount, create mode only).
     */
    protected function loadDraft(): bool
    {
        $key = 'survey_draft_'.$this->survey_template_id;
        $draft = session()->get($key);

        if (! $draft || empty($draft['responses'])) {
            return false;
        }

        $this->ship_id = $draft['ship_id'];
        $this->survey_date = $draft['survey_date'];
        $this->surveyor = $draft['surveyor'];
        $this->location = $draft['location'];
        $this->status = $draft['status'];
        $this->notes = $draft['notes'];

        // Merge draft responses with empty responses (in case template changed)
        foreach ($this->responses as $itemId => $empty) {
            if (isset($draft['responses'][$itemId])) {
                $this->responses[$itemId] = $draft['responses'][$itemId];
            }
        }

        $this->activeCategory = $draft['activeCategory'] ?? $this->activeCategory;

        return true;
    }

    /**
     * Clear draft after successful save.
     */
    protected function clearDraft(): void
    {
        session()->forget('survey_draft_'.$this->survey_template_id);
    }

    public function render()
    {
        return view('livewire.surveys.survey-form', [
            'categories' => $this->categories,
        ]);
    }
}
