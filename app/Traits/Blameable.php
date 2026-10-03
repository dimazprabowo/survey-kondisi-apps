<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit "who" columns: auto-isi created_by & updated_by dari user yang login.
 * Kolom hanya disimpan di DB (tidak untuk ditampilkan di index).
 * Nullable supaya seeder/console tanpa auth tetap jalan.
 */
trait Blameable
{
    protected static function bootBlameable(): void
    {
        static::creating(function (Model $model) {
            if ($userId = auth()->id()) {
                $model->created_by ??= $userId;
                $model->updated_by ??= $userId;
            }
        });

        static::updating(function (Model $model) {
            if ($userId = auth()->id()) {
                $model->updated_by = $userId;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
