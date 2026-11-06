<?php

namespace App\Models;

use App\Enums\ConfigCategory;
use App\Enums\ConfigDataType;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfiguration extends Model
{
    use Blameable, HasFactory;

    protected $fillable = [
        'key',
        'category',
        'value',
        'data_type',
        'description',
        'is_editable',
        'is_active',
    ];

    protected $casts = [
        'category' => ConfigCategory::class,
        'data_type' => ConfigDataType::class,
        'is_editable' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, ConfigCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    // Accessors
    public function getParsedValueAttribute()
    {
        return $this->data_type->cast($this->value);
    }

    // Static Methods
    public static function get(string $key, $default = null)
    {
        $raw = Cache::remember(self::cacheKey($key), 3600, function () use ($key) {
            $config = self::where('key', $key)->where('is_active', true)->first();

            return $config
                ? ['value' => $config->value, 'data_type' => $config->getRawOriginal('data_type')]
                : false;
        });

        return $raw === false
            ? $default
            : ConfigDataType::from($raw['data_type'])->cast((string) $raw['value']);
    }

    private static function cacheKey(string $key): string
    {
        return "system_config.{$key}";
    }

    public static function set(string $key, $value, string $category = 'general', string $dataType = 'string'): self
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
            $dataType = 'json';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
            $dataType = 'boolean';
        } elseif (is_int($value)) {
            $dataType = 'integer';
        }

        $config = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'category' => $category,
                'data_type' => $dataType,
                'is_active' => true,
            ]
        );

        Cache::forget(self::cacheKey($key));

        return $config;
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($config) {
            Cache::forget(self::cacheKey($config->key));
        });

        static::deleted(function ($config) {
            Cache::forget(self::cacheKey($config->key));
        });
    }
}
