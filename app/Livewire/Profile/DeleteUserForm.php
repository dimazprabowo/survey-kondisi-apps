<?php

namespace App\Livewire\Profile;

use App\Livewire\Actions\Logout;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, UserService $service): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = Auth::user();
        $logout();
        $service->delete($user);

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.profile.delete-user-form');
    }
}
