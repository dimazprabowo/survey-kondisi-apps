<?php

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;

class SurveyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('surveys_view');
    }

    public function view(User $user, Survey $survey): bool
    {
        return $user->can('surveys_view');
    }

    public function create(User $user): bool
    {
        return $user->can('surveys_create');
    }

    public function update(User $user, Survey $survey): bool
    {
        return $user->can('surveys_update');
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $user->can('surveys_delete');
    }

    public function exportExcel(User $user, Survey $survey): bool
    {
        return $user->can('surveys_export_excel');
    }

    public function exportPdf(User $user, Survey $survey): bool
    {
        return $user->can('surveys_export_pdf');
    }
}
