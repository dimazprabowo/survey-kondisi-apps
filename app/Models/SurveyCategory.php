<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyCategory extends Model
{
    protected $fillable = [
        'survey_template_id',
        'code',
        'label',
        'order_num',
    ];

    protected $casts = [
        'order_num' => 'integer',
    ];

    public $timestamps = false;

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(SurveySubCategory::class)->orderBy('order_num');
    }
}
