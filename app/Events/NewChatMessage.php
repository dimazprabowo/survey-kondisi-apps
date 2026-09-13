<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $chatMessage
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast to each participant's user channel
        $participantIds = DB::table('chat_participants')
            ->where('chat_id', $this->chatMessage->chat_id)
            ->pluck('user_id');

        return $participantIds->map(
            fn ($id) => new PrivateChannel('user.'.$id)
        )->all();
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->chatMessage->id,
            'chat_id' => $this->chatMessage->chat_id,
            'user_id' => $this->chatMessage->user_id,
            'user_name' => $this->chatMessage->user->name,
            'body' => $this->chatMessage->body,
            'type' => $this->chatMessage->type,
            'created_at' => $this->chatMessage->created_at->toISOString(),
        ];
    }
}
