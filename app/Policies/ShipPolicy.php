<?php

namespace App\Policies;

use App\Models\Ship;
use App\Models\User;

class ShipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ships_view');
    }

    public function view(User $user, Ship $ship): bool
    {
        return $user->can('ships_view');
    }

    public function create(User $user): bool
    {
        return $user->can('ships_create');
    }

    public function update(User $user, Ship $ship): bool
    {
        return $user->can('ships_update');
    }

    public function delete(User $user, Ship $ship): bool
    {
        if ($ship->surveys()->exists()) {
            return false;
        }

        return $user->can('ships_delete');
    }

    public function toggleStatus(User $user, Ship $ship): bool
    {
        return $user->can('ships_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('ships_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('ships_export_pdf');
    }
}
