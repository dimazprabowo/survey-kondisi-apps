<?php

namespace App\Enums;

enum SurveyAccessStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Approved => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::Rejected => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        };
    }
}
