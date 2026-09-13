<?php

namespace App\Models;

use App\Notifications\CustomResetPassword;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'pending_email',
        'company_id',
        'phone',
        'position',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class, 'chat_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->role($role);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Accessors
    public function getIsAdminAttribute(): bool
    {
        return $this->hasRole(['super admin', 'admin']);
    }

    public function getFullNameAttribute(): string
    {
        return $this->name.($this->position ? " ({$this->position})" : '');
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification()
    {
        // Use pending_email if exists, otherwise use current email
        return $this->pending_email ?? $this->email;
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        // Determine which email to send to
        $emailTo = $this->pending_email ?? $this->email;

        // Send notification directly to the specific email
        \Illuminate\Support\Facades\Notification::route('mail', $emailTo)
            ->notify(new CustomVerifyEmail($this));
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        // Determine which email to send to
        $emailTo = $this->pending_email ?? $this->email;

        // Send notification directly to the specific email
        \Illuminate\Support\Facades\Notification::route('mail', $emailTo)
            ->notify(new CustomResetPassword($token, $this));
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
