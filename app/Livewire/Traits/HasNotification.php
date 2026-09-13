<?php

namespace App\Livewire\Traits;

trait HasNotification
{
    protected function notifySuccess(string $message): void
    {
        $this->dispatch('notify', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->dispatch('notify', type: 'error', message: $message);
    }

    protected function notifyWarning(string $message): void
    {
        $this->dispatch('notify', type: 'warning', message: $message);
    }

    protected function notifyInfo(string $message): void
    {
        $this->dispatch('notify', type: 'info', message: $message);
    }

    protected function notifyValidationError(\Illuminate\Validation\ValidationException $e): void
    {
        $errors = $e->validator->errors()->all();
        $message = count($errors) > 1
            ? 'Terdapat '.count($errors).' kesalahan validasi'
            : $errors[0];

        $this->dispatch('notify', type: 'error', message: $message);
    }
}
