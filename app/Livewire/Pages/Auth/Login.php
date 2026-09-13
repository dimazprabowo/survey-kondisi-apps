<?php

namespace App\Livewire\Pages\Auth;

use App\Livewire\Forms\LoginForm;
use App\Livewire\Traits\HasNotification;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Login extends Component
{
    use HasNotification;

    public LoginForm $form;

    public bool $showPassword = false;

    /**
     * Toggle password visibility.
     */
    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    /**
     * Handle an incoming authentication request.
     * Flow: reCAPTCHA validation → Field validation → Credentials check → Success
     */
    public function login(): void
    {
        // Token is already in $this->form->recaptcha_token via wire:model

        try {
            // Step 1: Validate form fields
            $this->validate();

            // Step 2: Authenticate (includes reCAPTCHA verification inside)
            // This will throw ValidationException if reCAPTCHA fails
            $this->form->authenticate();

            // Step 3: Only reach here if BOTH reCAPTCHA and credentials are valid
            Session::regenerate();

            // Step 4: Dispatch success notification ONLY after everything passes
            $this->notifySuccess('Login berhasil! Mengalihkan...');

            // Step 5: Redirect
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Get first error message
            $errors = $e->validator->errors();
            $firstError = $errors->first();

            // Dispatch error notification for validation failures (including reCAPTCHA)
            $this->notifyError($firstError);

            // Re-throw to show field errors
            throw $e;
        } catch (\Exception $e) {
            // Dispatch generic error for unexpected errors
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');

            // Log the error for debugging
            \Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if reCAPTCHA is enabled
     */
    public function isRecaptchaEnabled(): bool
    {
        return config('services.recaptcha.enabled', false);
    }

    /**
     * Get reCAPTCHA site key
     */
    public function getRecaptchaSiteKey(): string
    {
        return config('services.recaptcha.site_key', '');
    }

    public function render()
    {
        return view('livewire.pages.auth.login');
    }
}
