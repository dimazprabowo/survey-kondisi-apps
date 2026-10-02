<?php

namespace App\Livewire\Surveys;

use App\Livewire\Traits\HasNotification;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveySubCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class SurveyShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public Survey $survey;

    public $activeCategory = 1;

    public $activeSubCategory;

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
        return SurveyCategory::with(['subCategories.itemGroups.items'])
            ->where('survey_template_id', $this->survey->survey_template_id)
            ->orderBy('order_num')
            ->get();
    }

    public function getResponsesProperty()
    {
        return \App\Models\SurveyResponse::where('survey_id', $this->survey->id)
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
     * Sub-category average dari responses yang sudah eager-loaded (tanpa query tambahan).
     */
    public function subCategoryAvg(SurveySubCategory $subCat): ?float
    {
        $igAvgs = [];
        foreach ($subCat->itemGroups as $itemGroup) {
            $itemAvgs = [];
            foreach ($itemGroup->items as $item) {
                $avg = $this->responses->get($item->id)?->avg_score;
                if ($avg !== null) {
                    $itemAvgs[] = (float) $avg;
                }
            }
            if (! empty($itemAvgs)) {
                $igAvgs[] = array_sum($itemAvgs) / count($itemAvgs);
            }
        }

        return empty($igAvgs) ? null : round(array_sum($igAvgs) / count($igAvgs), 2);
    }

    public function editSurvey()
    {
        $this->authorize('update', $this->survey);

        return $this->redirect(route('surveys.edit', $this->survey), navigate: true);
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
