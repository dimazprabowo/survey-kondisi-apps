<?php

namespace App\Livewire\Surveys;

use App\Livewire\Traits\HasNotification;
use App\Models\Survey;
use App\Models\SurveyCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class SurveyShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public Survey $survey;

    public $activeCategory = 1;

    public function mount(Survey $survey)
    {
        $this->authorize('view', $survey);
        $this->survey = $survey->load(['ship', 'creator', 'template']);
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

    public function setCategory($categoryId): void
    {
        $this->activeCategory = $categoryId;
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
        ]);
    }
}
