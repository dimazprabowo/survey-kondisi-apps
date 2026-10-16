<?php

namespace App\Models;

use App\Enums\FileStatus;
use App\Traits\Blameable;
use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SurveyReport extends Model
{
    use Blameable, HasEncryptedRouteKey, LogsActivity;

    protected $fillable = [
        'survey_id',
        'report_number',
        'report_title',
        'approval_place',
        'approval_date',
        'approver_name',
        'inspector_1',
        'inspector_2',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'file_error',
        'file_processed_at',
        'generator_version',
        'edited_file_path',
        'edited_file_name',
        'edited_file_size',
        'edited_at',
        'edited_by',
    ];

    protected $casts = [
        'approval_date' => 'date',
        'file_status' => FileStatus::class,
        'file_processed_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SurveyReportSection::class)->orderBy('order_num');
    }

    public function documentations(): HasMany
    {
        return $this->hasMany(SurveyReportDocumentation::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['report_number', 'report_title', 'file_status', 'edited_file_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('survey_report');
    }
}
