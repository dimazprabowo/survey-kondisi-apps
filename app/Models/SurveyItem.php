<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyItem extends Model
{
    protected $fillable = [
        'survey_item_group_id',
        'code',
        'name',
        'item_type',
        'score_labels',
        'has_date_fields',
        'order_num',
    ];

    protected $casts = [
        'survey_item_group_id' => 'integer',
        'item_type' => \App\Enums\SurveyItemType::class,
        'score_labels' => 'array',
        'has_date_fields' => 'boolean',
        'order_num' => 'integer',
    ];

    public $timestamps = false;

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(SurveyItemGroup::class, 'survey_item_group_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
