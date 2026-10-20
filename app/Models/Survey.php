<?php

namespace App\Models;

use App\Enums\CapRating;
use App\Enums\SurveyStatus;
use App\Traits\Blameable;
use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Survey extends Model
{
    use Blameable, HasEncryptedRouteKey, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'survey_number',
        'ship_id',
        'survey_template_id',
        'survey_folder_id',
        'structure',
        'ship_snapshot',
        'survey_date',
        'surveyor',
        'location',
        'status',
        'overall_cap_score',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => SurveyStatus::class,
        'survey_date' => 'date',
        'structure' => 'array',
        'ship_snapshot' => 'array',
        'overall_cap_score' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SurveyFolder::class, 'survey_folder_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function groupNotes(): HasMany
    {
        return $this->hasMany(SurveyGroupNote::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(SurveyReport::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(SurveyAccessRequest::class);
    }

    public function approvedAccessRequests(): HasMany
    {
        return $this->accessRequests()->approved();
    }

    /**
     * Pemilik survey (created_by). Nilai null (survey lama/seeder) dianggap
     * tanpa owner — aksi hanya dibatasi permission agar tidak lock-out.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === null || (int) $this->created_by === (int) $user->id;
    }

    /**
     * Boleh diedit oleh owner atau kolaborator dengan akses approved.
     * Memakai relasi yang sudah eager-loaded (di-filter user aktif) bila ada
     * untuk menghindari N+1 di halaman list.
     */
    public function isEditableBy(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->relationLoaded('approvedAccessRequests')
            ? $this->approvedAccessRequests->contains('user_id', $user->id)
            : $this->approvedAccessRequests()->where('user_id', $user->id)->exists();
    }

    public function scopeDraft($query)
    {
        return $query->where('status', SurveyStatus::Draft);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SurveyStatus::Completed);
    }

    /**
     * Nilai particulars kapal dari ship_snapshot; fallback ke relasi ship
     * live untuk survey lama yang belum punya snapshot.
     */
    public function shipSnapshotValue(string $key, mixed $default = null): mixed
    {
        return $this->ship_snapshot['attributes'][$key] ?? $this->ship?->{$key} ?? $default;
    }

    /**
     * Nama kapal untuk tampilan list/eksport — snapshot diprioritaskan agar
     * survey "Kapal Baru" (ship_id null) dan survey lama tetap terbaca.
     */
    public function getShipDisplayNameAttribute(): ?string
    {
        return $this->ship?->name ?? $this->ship_snapshot['attributes']['name'] ?? null;
    }

    /**
     * Grade kategori CAP maritim dari overall_cap_score (derived, bukan kolom).
     */
    public function capRating(): ?CapRating
    {
        return CapRating::fromScore($this->overall_cap_score !== null ? (float) $this->overall_cap_score : null);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['survey_number', 'ship_id', 'survey_template_id', 'survey_folder_id', 'survey_date', 'status', 'overall_cap_score'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('survey');
    }
}
