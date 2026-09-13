<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'pic_name',
        'pic_email',
        'pic_phone',
        'npwp',
        'status',
    ];

    protected $casts = [
        'status' => CompanyStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', CompanyStatus::Active);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    public function getNpwpFormattedAttribute(): ?string
    {
        if (! $this->npwp) {
            return null;
        }

        $npwp = preg_replace('/\D/', '', $this->npwp);
        $length = strlen($npwp);

        if ($length === 15) {
            // Format lama: XX.XXX.XXX.X-XXX.XXX
            return substr($npwp, 0, 2).'.'.
                   substr($npwp, 2, 3).'.'.
                   substr($npwp, 5, 3).'.'.
                   substr($npwp, 8, 1).'-'.
                   substr($npwp, 9, 3).'.'.
                   substr($npwp, 12, 3);
        } elseif ($length === 16) {
            // Format baru: XXXX.XXXX.XXXX.XXXX
            return substr($npwp, 0, 4).'.'.
                   substr($npwp, 4, 4).'.'.
                   substr($npwp, 8, 4).'.'.
                   substr($npwp, 12, 4);
        }

        return $npwp;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
