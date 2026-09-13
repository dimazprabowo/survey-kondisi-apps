<?php

namespace App\Livewire\Pages\Auth;

use App\Helpers\ConfigHelper;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

            $validated['password'] = Hash::make($validated['password']);

            event(new Registered($user = User::create($validated)));

            // Assign default role 'user' to newly registered user
            $user->assignRole('user');

            Auth::login($user);

            $this->dispatch('notify',
                type: 'success',
                message: 'Pendaftaran berhasil! Email verifikasi telah dikirim.'
            );

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
