<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyGroupNote extends Model
{
    protected $fillable = [
        'survey_id',
        'survey_item_group_id',
        'note',
        'order_num',
    ];

    public $timestamps = false;

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(SurveyItemGroup::class, 'survey_item_group_id');
    }
}
