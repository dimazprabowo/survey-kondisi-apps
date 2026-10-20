<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Konten survey berubah di editor lain — dikirim ke presence channel.
 * kind 'saved': DB diperbarui permanen; `version` = updated_at terbaru.
 * kind 'draft': field yang berubah ikut di `changes` (path => nilai) dan
 * langsung diterapkan remote; `structure` = true bila struktur berubah /
 * payload terlalu besar → remote memuat payload penuh dari cache.
 */
class SurveyContentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $surveyId,
        public int $userId,
        public string $userName,
        public string $kind = 'saved',
        public array $changes = [],
        public bool $structureChanged = false,
        public ?string $version = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('survey.'.$this->surveyId)];
    }

    public function broadcastWith(): array
    {
        return [
            'survey_id' => $this->surveyId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'kind' => $this->kind,
            // Objek (bukan array JSON) agar key path tetap utuh di client
            'changes' => (object) $this->changes,
            'structure' => $this->structureChanged,
            'version' => $this->version,
            'saved_at' => now()->toIso8601String(),
        ];
    }
}
