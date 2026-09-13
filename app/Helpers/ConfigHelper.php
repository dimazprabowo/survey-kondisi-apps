<?php

namespace App\Helpers;

use App\Models\SystemConfiguration;

class ConfigHelper
{
    /**
     * Get system configuration value
     */
    public static function get(string $key, $default = null)
    {
        return SystemConfiguration::get($key, $default);
    }

    /**
     * Check if registration is currently open
     * Registration is open when:
     * 1. registration.deadline has a value (not empty)
     * 2. registration.deadline is_active is true
     * 3. Current date/time is before the deadline
     */
    public static function isRegistrationOpen(): bool
    {
        $config = SystemConfiguration::where('key', 'registration.deadline')
            ->where('is_active', true)
            ->first();

        if (! $config) {
            return false;
        }

        if (empty($config->value)) {
            return false;
        }

        try {
            $deadlineDate = \Carbon\Carbon::parse($config->value);

            return \Carbon\Carbon::now()->lt($deadlineDate);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get registration deadline as Carbon instance
     */
    public static function getRegistrationDeadline(): ?\Carbon\Carbon
    {
        $config = SystemConfiguration::where('key', 'registration.deadline')
            ->where('is_active', true)
            ->first();

        if (! $config || empty($config->value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($config->value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get formatted registration deadline
     */
    public static function getFormattedRegistrationDeadline(string $format = 'd F Y, H:i'): ?string
    {
        $deadline = self::getRegistrationDeadline();

        return $deadline ? $deadline->translatedFormat($format) : null;
    }

    /**
     * Get days remaining until registration deadline
     */
    public static function getRegistrationDaysRemaining(): ?int
    {
        $deadline = self::getRegistrationDeadline();
        if (! $deadline) {
            return null;
        }

        $days = \Carbon\Carbon::now()->diffInDays($deadline, false);

        return max(0, (int) $days);
    }

    /**
     * Check if should show registration countdown (within 7 days of deadline)
     */
    public static function shouldShowRegistrationCountdown(): bool
    {
        $days = self::getRegistrationDaysRemaining();

        return $days !== null && $days <= 7;
    }

    /**
     * Get registration closed message
     */
    public static function getRegistrationClosedMessage(): string
    {
        return SystemConfiguration::get('registration.closed_message', 'Pendaftaran telah ditutup.');
    }

    /**
     * Get application name from system configuration
     */
    public static function getAppName(): string
    {
        return SystemConfiguration::get('app.name', config('app.name', 'Boilerplate'));
    }
}
