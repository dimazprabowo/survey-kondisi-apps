<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyReportSection extends Model
{
    protected $fillable = [
        'survey_report_id',
        'key',
        'content',
        'order_num',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SurveyReport::class, 'survey_report_id');
    }
}
