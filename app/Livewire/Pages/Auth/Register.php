<?php

namespace App\Livewire\Pages\Auth;

use App\Helpers\ConfigHelper;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        if (! ConfigHelper::isRegistrationOpen()) {
            $this->dispatch('notify',
                type: 'error',
                message: ConfigHelper::getRegistrationClosedMessage()
            );
            $this->redirect(route('login'), navigate: true);

            return;
        }

        try {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ]);

            // Buat akun + role dalam SATU transaksi, lalu login. Email
            // verifikasi dikirim SETELAH commit — bila SMTP gagal, akun
            // tetap utuh (bukan "orphan" yang memblokir daftar ulang via
            // error 'email sudah terdaftar').
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                // Assign default role 'user' to newly registered user
                $user->assignRole('user');

                return $user;
            });

            Auth::login($user);

            try {
                event(new Registered($user));

                $this->dispatch('notify',
                    type: 'success',
                    message: 'Pendaftaran berhasil! Email verifikasi telah dikirim.'
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim email verifikasi setelah registrasi', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $this->dispatch('notify',
                    type: 'warning',
                    message: 'Akun berhasil dibuat, namun email verifikasi gagal dikirim. Gunakan tombol kirim ulang pada halaman verifikasi email.'
                );
            }

            $this->redirect(route('dashboard', absolute: false), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.'
            );
        }
    }

    public function render()
    {
        return view('livewire.pages.auth.register');
    }
}
