<?php

namespace App\Enums;

enum ShipStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'gray',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::Inactive => 'bg-gray-100 text-gray-800 dark:bg-gray-700/50 dark:text-gray-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
