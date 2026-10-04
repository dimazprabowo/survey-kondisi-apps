<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipCertificate extends Model
{
    protected $fillable = [
        'ship_id',
        'certificate_type',
        'last_date',
        'next_1_date',
        'next_2_date',
        'postpone_date',
        'order_num',
    ];

    protected $casts = [
        'last_date' => 'date',
        'next_1_date' => 'date',
        'next_2_date' => 'date',
        'postpone_date' => 'date',
    ];

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }
}
