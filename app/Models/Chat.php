<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_group',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
        ];
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants')
            ->withPivot('last_read_at', 'deleted_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    // Scopes
    public function scopeForUser($query, ?int $userId)
    {
        return $query->whereHas('participants', fn ($q) => $q->where('users.id', $userId ?? 0));
    }

    // Helpers
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_group) {
            return $this->name ?? 'Group Chat';
        }

        $otherParticipant = $this->participants
            ->where('id', '!=', auth()->id())
            ->first();

        return $otherParticipant?->name ?? 'Unknown';
    }

    public function getUnreadCountForUser(int $userId): int
    {
        // Use already-loaded participants relation to avoid extra query
        $participant = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('id', $userId)
            : $this->participants()->where('users.id', $userId)->first();

        if (! $participant) {
            return 0;
        }

        $lastReadAt = $participant->pivot->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
            ->count();
    }

    public static function findDirectChat(int $userA, int $userB): ?self
    {
        return static::where('is_group', false)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userB))
            ->first();
    }
}
