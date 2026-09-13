<?php

namespace App\Livewire\Pages\Auth;

use App\Livewire\Traits\HasNotification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ForgotPassword extends Component
{
    use HasNotification;

    public string $email = '';

    public bool $emailSent = false;

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        try {
            // Check if email exists in either email or pending_email
            $user = \App\Models\User::where('email', $this->email)
                ->orWhere('pending_email', $this->email)
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => 'Email tidak ditemukan dalam sistem kami.',
                ]);
            }

            // Generate password reset token
            $token = app('auth.password.broker')->createToken($user);

            // Send password reset notification
            $user->sendPasswordResetNotification($token);

            $this->emailSent = true;

            // Show which email received the link
            $sentTo = $user->pending_email ?? $user->email;

            $this->notifySuccess('Link reset password telah dikirim ke '.$sentTo);

        } catch (ValidationException $e) {
            $this->notifyError($e->validator->errors()->first());

            throw $e;
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan. Silakan coba lagi.');

            \Log::error('Password reset error', [
                'error' => $e->getMessage(),
                'email' => $this->email,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function render()
    {
        return view('livewire.pages.auth.forgot-password');
    }
}
