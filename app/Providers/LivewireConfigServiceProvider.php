<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LivewireConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set Livewire temporary file upload rules dynamically from system configuration
        // Use maximum values from all field configurations since temp upload is a global endpoint
        $maxSize = $this->getMaxUploadSizeFromAllFields();
        $allMimes = $this->getAllowedMimesFromAllFields();

        config([
            'livewire.temporary_file_upload.rules' => [
                'required',
                'file',
                'max:'.$maxSize,
                'mimes:'.$allMimes,
            ],
        ]);
    }

    /**
     * Get maximum upload size from all field configurations
     */
    private function getMaxUploadSizeFromAllFields(): int
    {
        $fields = config('file_upload.fields', []);
        $default = config('file_upload.default.max_size', 2048);

        $maxSize = $default;

        foreach ($fields as $fieldConfig) {
            if (isset($fieldConfig['max_size'])) {
                $maxSize = max($maxSize, $fieldConfig['max_size']);
            }
        }

        return $maxSize;
    }

    /**
     * Get all allowed MIME types from all field configurations (unique, comma-separated)
     */
    private function getAllowedMimesFromAllFields(): string
    {
        $fields = config('file_upload.fields', []);
        $default = config('file_upload.default.mimes', []);

        $allMimes = $default;

        foreach ($fields as $fieldConfig) {
            if (isset($fieldConfig['mimes'])) {
                $allMimes = array_merge($allMimes, $fieldConfig['mimes']);
            }
        }

        // Remove duplicates and return as comma-separated string
        return implode(',', array_unique($allMimes));
    }
}
