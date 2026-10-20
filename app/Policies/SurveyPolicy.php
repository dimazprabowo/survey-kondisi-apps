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
        return $user->can('surveys_update') && $survey->isEditableBy($user);
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $user->can('surveys_delete') && $survey->isOwnedBy($user);
    }

    /**
     * Mengelola permintaan & daftar kolaborator — hanya pemilik survey.
     * Super admin tetap lolos via Gate::before.
     */
    public function manageAccess(User $user, Survey $survey): bool
    {
        return $survey->created_by !== null
            && (int) $survey->created_by === (int) $user->id;
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('surveys_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('surveys_export_pdf');
    }
}
