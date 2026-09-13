<?php

namespace App\Policies;

use App\Models\SurveyTemplate;
use App\Models\User;

class SurveyTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('survey_templates_view');
    }

    public function view(User $user, SurveyTemplate $template): bool
    {
        return $user->can('survey_templates_view');
    }

    public function create(User $user): bool
    {
        return $user->can('survey_templates_create');
    }

    public function update(User $user, SurveyTemplate $template): bool
    {
        return $user->can('survey_templates_update');
    }

    public function delete(User $user, SurveyTemplate $template): bool
    {
        return $user->can('survey_templates_delete');
    }

    public function duplicate(User $user, SurveyTemplate $template): bool
    {
        return $user->can('survey_templates_duplicate');
    }

    public function toggleStatus(User $user, SurveyTemplate $template): bool
    {
        return $user->can('survey_templates_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('survey_templates_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('survey_templates_export_pdf');
    }
}
