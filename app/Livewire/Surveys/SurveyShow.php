<?php

namespace App\Livewire\Surveys;

use App\Livewire\Traits\HasNotification;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Services\SurveyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class SurveyShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public Survey $survey;

    public $activeCategory = 1;

    public $activeSubCategory;

    // Per-request cache responses (tidak diserialisasi Livewire)
    protected $responsesCache = null;

    public function mount(Survey $survey)
    {
        $this->authorize('view', $survey);
        $this->survey = $survey->load(['ship', 'creator', 'template']);

        $firstCat = $this->categories->first();
        $this->activeCategory = $firstCat?->id ?? 1;
        $this->activeSubCategory = $firstCat?->subCategories->first()?->id;
    }

    public function getCategoriesProperty()
    {
        // Render dari snapshot tersimpan — tidak terpengaruh
        // perubahan/penghapusan master template.
        if ($this->survey->structure) {
            return app(SurveyService::class)->hydrateStructure($this->survey->structure);
        }

        return SurveyCategory::with(['subCategories.itemGroups.items'])
            ->where('survey_template_id', $this->survey->survey_template_id)
            ->orderBy('order_num')
            ->get();
    }

    public function getResponsesProperty()
    {
        // Memoize per-request — dipakai berulang oleh avg calculators di Blade.
        return $this->responsesCache ??= \App\Models\SurveyResponse::where('survey_id', $this->survey->id)
            ->get()
            ->keyBy('survey_item_id');
    }

    public function getGroupNotesProperty()
    {
        return \App\Models\SurveyGroupNote::where('survey_id', $this->survey->id)
            ->orderBy('order_num')
            ->get()
            ->groupBy('survey_item_group_id');
    }

    public function setCategory($categoryId): void
    {
        $this->activeCategory = $categoryId;
        $this->activeSubCategory = $this->categories
            ->firstWhere('id', $categoryId)
            ?->subCategories->first()?->id;
    }

    public function setSubCategory($subCategoryId): void
    {
        $this->activeSubCategory = $subCategoryId;
    }

    /**
     * Item group average dari responses yang sudah eager-loaded (tanpa query tambahan).
     * Delegasi ke SurveyService — single source of truth perhitungan rata-rata.
     */
    public function itemGroupAvg($itemGroup): ?float
    {
        return app(SurveyService::class)->itemGroupAvg($itemGroup, $this->responses);
    }

    /**
     * Sub-category average (average of item group averages).
     */
    public function subCategoryAvg($subCat): ?float
    {
        return app(SurveyService::class)->subCategoryAvg($subCat, $this->responses);
    }

    /**
     * Category average (average of sub-category averages).
     */
    public function categoryAvg($cat): ?float
    {
        return app(SurveyService::class)->categoryAvg($cat, $this->responses);
    }

    public function editSurvey()
    {
        $this->authorize('update', $this->survey);

        return $this->redirect(route('surveys.edit', $this->survey), navigate: true);
    }

    public function openReport()
    {
        $this->authorize('viewAny', \App\Models\SurveyReport::class);

        return $this->redirect(route('surveys.report', $this->survey), navigate: true);
    }

    public function render()
    {
        return view('livewire.surveys.survey-show', [
            'categories' => $this->categories,
            'responses' => $this->responses,
            'groupNotes' => $this->groupNotes,
        ]);
    }
}
