<?php

namespace App\Models;

use App\Enums\SurveyAccessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SurveyAccessRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'survey_id',
        'user_id',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'status' => SurveyAccessStatus::class,
        'decided_at' => 'datetime',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', SurveyAccessStatus::Pending);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', SurveyAccessStatus::Approved);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['survey_id', 'user_id', 'status', 'decided_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('survey_access_request');
    }
}
