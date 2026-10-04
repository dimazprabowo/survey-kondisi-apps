<?php

namespace App\Enums;

enum FileStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Processing => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::Failed => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
