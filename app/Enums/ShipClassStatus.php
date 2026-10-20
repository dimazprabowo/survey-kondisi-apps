<?php

namespace App\Enums;

enum ShipClassStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Suspended => 'amber',
            self::Withdrawn => 'red',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::Suspended => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Withdrawn => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
