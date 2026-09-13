<?php

namespace App\Livewire\Profile;

use App\Livewire\Traits\HasNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UpdateProfileInformationForm extends Component
{
    use HasNotification;

    public string $name = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        try {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)->where(function ($query) {
                    return $query->whereNull('pending_email')->orWhere('pending_email', '!=', request('email'));
                })],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        $user->name = $validated['name'];

        $emailChanged = false;
        $currentEmail = $user->email;
        $newEmail = $validated['email'];

        if ($newEmail !== $currentEmail) {
            if ($user->pending_email !== $newEmail) {
                $user->pending_email = $newEmail;
                $emailChanged = true;
            }
        } else {
            if ($user->pending_email) {
                $user->pending_email = null;
            }
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            Session::flash('status', 'verification-link-sent');

            $this->dispatch('notify',
                type: 'info',
                message: 'Email verifikasi telah dikirim ke '.$newEmail.'. Silakan verifikasi untuk mengaktifkan email baru.'
            );
        } else {
            $this->dispatch('notify',
                type: 'success',
                message: 'Profil berhasil diperbarui!'
            );
        }

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if (! $user->pending_email && $user->hasVerifiedEmail()) {
            $this->dispatch('notify',
                type: 'info',
                message: 'Email Anda sudah terverifikasi.'
            );

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');

        $emailSentTo = $user->pending_email ?? $user->email;

        $this->dispatch('notify',
            type: 'success',
            message: 'Email verifikasi telah dikirim ulang ke '.$emailSentTo.'!'
        );
    }

    public function render()
    {
        return view('livewire.profile.update-profile-information-form', [
            'authUser' => Auth::user(),
        ]);
    }
}
