<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $chatId,
        public int $userId,
        public string $userName,
        public bool $isTyping = true,
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast to each participant's user channel
        $participantIds = DB::table('chat_participants')
            ->where('chat_id', $this->chatId)
            ->where('user_id', '!=', $this->userId)
            ->pluck('user_id');

        return $participantIds->map(
            fn ($id) => new PrivateChannel('user.'.$id)
        )->all();
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'is_typing' => $this->isTyping,
        ];
    }
}
