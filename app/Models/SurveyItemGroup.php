<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyItemGroup extends Model
{
    protected $fillable = [
        'survey_sub_category_id',
        'code',
        'name',
        'order_num',
    ];

    protected $casts = [
        'survey_sub_category_id' => 'integer',
        'order_num' => 'integer',
    ];

    public $timestamps = false;

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SurveySubCategory::class, 'survey_sub_category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SurveyItem::class)->orderBy('order_num');
    }
}
