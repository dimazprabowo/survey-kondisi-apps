<?php

namespace App\Models;

use App\Enums\ShipStatus;
use App\Traits\Blameable;
use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ship extends Model
{
    use Blameable, HasEncryptedRouteKey, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'year_built',
        'imo_number',
        'ship_type',
        'flag',
        'gross_tonnage',
        'owner',
        'operator',
        'call_sign',
        'net_tonnage',
        'loa',
        'lpp',
        'breadth',
        'depth',
        'draft',
        'dwt',
        'builder',
        'port_of_registry',
        'hull_material',
        'class_name',
        'class_notations',
        'main_engine',
        'main_engine_power',
        'aux_engine',
        'aux_engine_power',
        'status',
    ];

    protected $casts = [
        'status' => ShipStatus::class,
        'year_built' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(ShipCertificate::class)->orderBy('order_num');
    }

    public function scopeActive($query)
    {
        return $query->where('status', ShipStatus::Active);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === ShipStatus::Active;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->year_built ? (int) now()->format('Y') - $this->year_built : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'year_built', 'imo_number', 'ship_type', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ship');
    }
}
