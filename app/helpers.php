<?php

if (! function_exists('email_logo_url')) {
    /**
     * Get the public URL for the email logo
     * Logo is hosted on DigitalOcean Spaces (S3) for email client compatibility
     */
    function email_logo_url(): string
    {
        return 'https://statutoria-monitoring-bucket.sgp1.digitaloceanspaces.com/assets/bki-logo.png';
    }
}

if (! function_exists('get_max_upload_size')) {
    /**
     * Get maximum upload size in KB for a specific field
     *
     * @param  string|null  $fieldName  Field name from config/file_upload.php
     * @return int Size in KB
     */
    function get_max_upload_size(?string $fieldName = null): int
    {
        $config = config('file_upload.fields.'.$fieldName, config('file_upload.default'));

        return (int) $config['max_size'];
    }
}

if (! function_exists('get_allowed_mimes')) {
    /**
     * Get allowed MIME types for a specific field
     * Returns comma-separated string
     *
     * @param  string|null  $fieldName  Field name from config/file_upload.php
     */
    function get_allowed_mimes(?string $fieldName = null): string
    {
        $config = config('file_upload.fields.'.$fieldName, config('file_upload.default'));

        return implode(',', $config['mimes']);
    }
}

if (! function_exists('get_allowed_mimes_array')) {
    /**
     * Get allowed MIME types as array for a specific field
     *
     * @param  string|null  $fieldName  Field name from config/file_upload.php
     */
    function get_allowed_mimes_array(?string $fieldName = null): array
    {
        $config = config('file_upload.fields.'.$fieldName, config('file_upload.default'));

        return $config['mimes'];
    }
}

if (! function_exists('file_upload_validation_rule')) {
    /**
     * Get complete file upload validation rule string for a specific field
     * Example: "nullable|file|max:2048|mimes:jpg,jpeg,png,pdf"
     *
     * @param  string|null  $fieldName  Field name from config/file_upload.php (e.g., 'photo-config', 'file-config')
     * @param  bool  $required  Whether file is required
     */
    function file_upload_validation_rule(?string $fieldName = null, bool $required = false): string
    {
        $rules = [];

        $rules[] = $required ? 'required' : 'nullable';
        $rules[] = 'file';
        $rules[] = 'max:'.get_max_upload_size($fieldName);
        $rules[] = 'mimes:'.get_allowed_mimes($fieldName);

        return implode('|', $rules);
    }
}

if (! function_exists('get_upload_config_display')) {
    /**
     * Get human-readable upload configuration for display
     * Example: "Max: 2 MB | Types: JPG, JPEG, PNG, PDF"
     *
     * @param  string|null  $fieldName  Field name from config/file_upload.php
     */
    function get_upload_config_display(?string $fieldName = null): string
    {
        $maxSizeKB = get_max_upload_size($fieldName);
        $maxSizeMB = $maxSizeKB / 1024;
        $mimes = strtoupper(str_replace(',', ', ', get_allowed_mimes($fieldName)));

        return "Max: {$maxSizeMB} MB | Types: {$mimes}";
    }
}

// ============================================================================
// System Configuration Helpers
// ============================================================================

if (! function_exists('app_name')) {
    /**
     * Get application name from system configuration
     */
    function app_name(): string
    {
        return \App\Helpers\ConfigHelper::getAppName();
    }
}

if (! function_exists('system_config')) {
    /**
     * Get system configuration value
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $default  Default value if not found
     * @return mixed
     */
    function system_config(string $key, $default = null)
    {
        return \App\Helpers\ConfigHelper::get($key, $default);
    }
}

if (! function_exists('is_registration_open')) {
    /**
     * Check if registration is currently open
     */
    function is_registration_open(): bool
    {
        return \App\Helpers\ConfigHelper::isRegistrationOpen();
    }
}

if (! function_exists('registration_deadline')) {
    /**
     * Get registration deadline as Carbon instance
     */
    function registration_deadline(): ?\Carbon\Carbon
    {
        return \App\Helpers\ConfigHelper::getRegistrationDeadline();
    }
}

if (! function_exists('registration_closed_message')) {
    /**
     * Get registration closed message
     */
    function registration_closed_message(): string
    {
        return \App\Helpers\ConfigHelper::getRegistrationClosedMessage();
    }
}

if (! function_exists('file_disk')) {
    /**
     * Get the configured filesystem disk for permanent file storage.
     * Uses Laravel's default filesystem disk (FILESYSTEM_DISK in .env).
     */
    function file_disk(): string
    {
        return config('filesystems.default', 'local');
    }
}

if (! function_exists('auth_layout_position')) {
    /**
     * Get the validated authentication layout position.
     *
     * Reads from config('auth_layout.position') which is sourced from
     * LOGIN_POSITION in .env. Invalid values fall back to "center".
     *
     * @return string One of: 'left', 'center', 'right'
     */
    function auth_layout_position(): string
    {
        $position = strtolower((string) config('auth_layout.position', 'center'));

        return in_array($position, ['left', 'center', 'right'], true)
            ? $position
            : 'center';
    }
}

if (! function_exists('upload_path')) {
    /**
     * Build a permanent storage path following the app-wide convention:
     *   {app_env}/{feature}/{...segments}/{slug-name}_{YmdHis}.{ext}
     *
     * Centralized via FileStorageService to avoid duplicating path logic.
     *
     * @param  string  $feature  Menu/feature name, e.g. 'personel-certificates'
     * @param  array<string>  $segments  Extra path segments (auto-slugged), e.g. [$itemSlug]
     * @param  string  $originalName  Original file name (used for extension & base name)
     * @param  string|null  $baseName  Override base file name (default: from originalName)
     */
    function upload_path(string $feature, array $segments, string $originalName, ?string $baseName = null): string
    {
        return app(\App\Services\FileStorageService::class)
            ->buildPath($feature, $segments, $originalName, $baseName);
    }
}

if (! function_exists('survey_score_color')) {
    /**
     * Get the color key for a survey CAP score.
     * Returns: 'green' (>=3.5), 'blue' (>=2.5), 'amber' (>=1.5), 'red' (<1.5), or null.
     */
    function survey_score_color(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return $score >= 3.5 ? 'green' : ($score >= 2.5 ? 'blue' : ($score >= 1.5 ? 'amber' : 'red'));
    }
}

if (! function_exists('survey_score_badge_class')) {
    /**
     * Get the Tailwind badge class for a survey CAP score.
     * Single source of truth for score badge styling (used by survey-form & survey-cap-rating).
     */
    function survey_score_badge_class(?float $score): string
    {
        $classes = [
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        ];

        return $classes[survey_score_color($score) ?? 'red'] ?? $classes['red'];
    }
}
