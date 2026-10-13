<?php

namespace App\Policies;

use App\Models\SurveyFolder;
use App\Models\User;

class SurveyFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('surveys_view');
    }

    public function view(User $user, SurveyFolder $folder): bool
    {
        return $user->can('surveys_view');
    }

    public function create(User $user): bool
    {
        return $user->can('survey_folders_create');
    }

    public function update(User $user, SurveyFolder $folder): bool
    {
        return $user->can('survey_folders_update');
    }

    public function delete(User $user, SurveyFolder $folder): bool
    {
        return $user->can('survey_folders_delete');
    }
}
