<?php

namespace App\Policies;

use App\Models\SurveyReport;
use App\Models\User;

class SurveyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('survey_reports_view');
    }

    public function view(User $user, SurveyReport $report): bool
    {
        return $user->can('survey_reports_view');
    }

    public function update(User $user, SurveyReport $report): bool
    {
        return $user->can('survey_reports_update');
    }

    public function generate(User $user, SurveyReport $report): bool
    {
        return $user->can('survey_reports_generate');
    }
}
