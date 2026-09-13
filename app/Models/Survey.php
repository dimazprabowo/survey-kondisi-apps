<?php

namespace App\Models;

use App\Enums\SurveyStatus;
use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Survey extends Model
{
    use HasEncryptedRouteKey, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'survey_number',
        'ship_id',
        'survey_template_id',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', SurveyStatus::Draft);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SurveyStatus::Completed);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['survey_number', 'ship_id', 'survey_template_id', 'survey_date', 'status', 'overall_cap_score'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('survey');
    }
}
