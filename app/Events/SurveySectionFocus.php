<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Posisi tab sub-kategori editor — dipakai untuk menampilkan chip inisial
 * kolaborator pada tab yang sedang mereka kerjakan (awareness ala Sheets).
 */
class SurveySectionFocus implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $surveyId,
        public int $userId,
        public string $userName,
        public ?int $subCategoryId,
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
            'sub_category_id' => $this->subCategoryId,
        ];
    }
}
