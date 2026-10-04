<?php

namespace App\Models;

use App\Enums\FileStatus;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SurveyReportDocumentation extends Model
{
    use Blameable, LogsActivity;

    protected $fillable = [
        'survey_report_id',
        'survey_category_id',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'file_error',
        'file_processed_at',
        'crop_data',
    ];

    protected $casts = [
        'file_status' => FileStatus::class,
        'file_processed_at' => 'datetime',
        'crop_data' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SurveyReport::class, 'survey_report_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['survey_report_id', 'survey_category_id', 'file_path', 'file_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('survey_report_documentation');
    }
}
