<?php

namespace App\Enums;

use Carbon\Carbon;

enum ConfigDataType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';
    case Array = 'array';
    case Datetime = 'datetime';

    public function label(): string
    {
        return match ($this) {
            self::String => 'String',
            self::Integer => 'Integer',
            self::Boolean => 'Boolean',
            self::Json => 'JSON',
            self::Array => 'Array',
            self::Datetime => 'Datetime',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->toArray();
    }

    public function cast(string $value): mixed
    {
        return match ($this) {
            self::Integer => (int) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode($value, true),
            self::Array => explode(',', $value),
            self::Datetime => $value ? Carbon::parse($value) : null,
            default => $value,
        };
    }
}
