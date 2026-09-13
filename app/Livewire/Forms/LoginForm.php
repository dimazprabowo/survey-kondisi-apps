<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    public string $recaptcha_token = '';

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Flow:
     * 1. Verify reCAPTCHA (FIRST - most important security check)
     * 2. Check rate limiting
     * 3. Attempt authentication with credentials
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        // STEP 1: Verify reCAPTCHA FIRST before anything else (if enabled)
        // This prevents bot attacks and ensures human verification
        if (config('services.recaptcha.enabled')) {
            $this->verifyRecaptcha();
        }

        // STEP 2: Check rate limiting (after reCAPTCHA to prevent abuse)
        $this->ensureIsNotRateLimited();

        // STEP 3: Attempt authentication with credentials
        // Only reach here if reCAPTCHA passed
        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => 'Email atau password salah. Silakan coba lagi.',
            ]);
        }

        // STEP 4: Check if user is active
        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'form.email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.',
            ]);
        }

        // Success - clear rate limiter
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Verify reCAPTCHA v2 token.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function verifyRecaptcha(): void
    {
        // Check if token exists
        if (empty($this->recaptcha_token)) {
            throw ValidationException::withMessages([
                'form.recaptcha_token' => 'Silakan centang reCAPTCHA untuk melanjutkan.',
            ]);
        }

        // Verify with Google
        try {
            $response = Http::timeout(10)->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $this->recaptcha_token,
                'remoteip' => request()->ip(),
            ]);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'form.recaptcha_token' => 'Gagal menghubungi server reCAPTCHA. Silakan coba lagi.',
                ]);
            }

            $result = $response->json();

            // Check if verification was successful
            if (! isset($result['success']) || ! $result['success']) {
                $errorCodes = $result['error-codes'] ?? [];
                \Log::error('reCAPTCHA v2 verification failed', [
                    'error_codes' => $errorCodes,
                    'token' => substr($this->recaptcha_token, 0, 20).'...',
                ]);

                throw ValidationException::withMessages([
                    'form.recaptcha_token' => 'Verifikasi reCAPTCHA gagal. Silakan coba lagi.',
                ]);
            }

            // reCAPTCHA v2 doesn't have score, just success/fail
            // Log successful verification
            \Log::info('reCAPTCHA v2 verification successful', [
                'ip' => request()->ip(),
                'hostname' => $result['hostname'] ?? 'unknown',
            ]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            \Log::error('reCAPTCHA request failed', [
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'form.recaptcha_token' => 'Gagal verifikasi reCAPTCHA. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
