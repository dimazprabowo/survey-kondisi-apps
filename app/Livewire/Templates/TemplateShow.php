<?php

namespace App\Livewire\Templates;

use App\Livewire\Traits\HasNotification;
use App\Models\SurveyTemplate;
use App\Services\SurveyTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class TemplateShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?SurveyTemplate $template = null;

    public $templateId;

    public function mount(SurveyTemplate $template)
    {
        $this->authorize('view', $template);
        $this->template = $template;
        $this->templateId = $template->id;
    }

    public function editTemplate()
    {
        $template = SurveyTemplate::findOrFail($this->templateId);
        $this->authorize('update', $template);

        return $this->redirect(route('master-data.survey-templates.edit', $template), navigate: true);
    }

    public function backToList()
    {
        return $this->redirect(route('master-data.survey-templates.index'), navigate: true);
    }

    public function render(SurveyTemplateService $service)
    {
        $template = $service->getWithHierarchy($this->templateId);

        return view('livewire.templates.template-show', compact('template'));
    }
}
