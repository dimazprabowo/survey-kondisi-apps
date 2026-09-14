<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id',
        'survey_item_id',
        'scores',
        'avg_score',
        'date_issued',
        'date_expired',
        'qty',
        'specification',
        'note',
    ];

    protected $casts = [
        'scores' => 'array',
        'avg_score' => 'decimal:2',
        'date_issued' => 'date',
        'date_expired' => 'date',
    ];

    public $timestamps = false;

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SurveyItem::class, 'survey_item_id');
    }
}
