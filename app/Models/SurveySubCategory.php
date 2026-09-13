<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySubCategory extends Model
{
    protected $fillable = [
        'survey_category_id',
        'order_num',
        'name',
    ];

    protected $casts = [
        'survey_category_id' => 'integer',
        'order_num' => 'integer',
    ];

    public $timestamps = false;

    public function category(): BelongsTo
    {
        return $this->belongsTo(SurveyCategory::class, 'survey_category_id');
    }

    public function itemGroups(): HasMany
    {
        return $this->hasMany(SurveyItemGroup::class)->orderBy('order_num');
    }
}
