<?php

namespace App\Livewire\Pages\Auth;

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class VerifyEmail extends Component
{
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->dispatch('notify',
                type: 'info',
                message: 'Email Anda sudah terverifikasi.'
            );
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        try {
            Auth::user()->sendEmailVerificationNotification();

            Session::flash('status', 'verification-link-sent');

            $this->dispatch('notify',
                type: 'success',
                message: 'Link verifikasi berhasil dikirim! Silakan cek email Anda.'
            );
        } catch (\Exception $e) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Gagal mengirim email verifikasi. Silakan coba lagi.'
            );
        }
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.auth.verify-email');
    }
}
