<?php

namespace App\Enums;

/**
 * Grade kategori CAP (Condition Assessment Programme) maritim berdasarkan
 * overall_cap_score (skala 0-4, semakin tinggi semakin baik):
 * CAP 1 = terbaik (>=3.5), CAP 2 (>=2.5), CAP 3 (>=1.5), CAP 4 (<1.5).
 *
 * Single source of truth untuk threshold, warna, dan label kategori —
 * helper survey_score_color()/survey_score_badge_class() mendelegasikan
 * ke enum ini agar tidak ada threshold duplikat.
 */
enum CapRating: int
{
    case Cap1 = 1;
    case Cap2 = 2;
    case Cap3 = 3;
    case Cap4 = 4;

    public static function fromScore(?float $score): ?self
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 3.5 => self::Cap1,
            $score >= 2.5 => self::Cap2,
            $score >= 1.5 => self::Cap3,
            default => self::Cap4,
        };
    }

    public function label(): string
    {
        return 'CAP '.$this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::Cap1 => 'Sangat Baik',
            self::Cap2 => 'Baik',
            self::Cap3 => 'Cukup Memuaskan',
            self::Cap4 => 'Kurang Memuaskan',
        };
    }

    /**
     * Kunci warna yang sama dengan mapping badge lama (>=3.5 hijau,
     * >=2.5 biru, >=1.5 amber, <1.5 merah).
     */
    public function color(): string
    {
        return match ($this) {
            self::Cap1 => 'green',
            self::Cap2 => 'blue',
            self::Cap3 => 'amber',
            self::Cap4 => 'red',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Cap1 => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::Cap2 => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            self::Cap3 => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Cap4 => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        };
    }
}
