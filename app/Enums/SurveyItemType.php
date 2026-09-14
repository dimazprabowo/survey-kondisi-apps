<?php

namespace App\Enums;

enum SurveyItemType: string
{
    case Score = 'score';
    case Inventory = 'inventory';

    public function label(): string
    {
        return match ($this) {
            self::Score => 'Skor (C/V/F/M)',
            self::Inventory => 'Inventaris (Qty & Spesifikasi)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
