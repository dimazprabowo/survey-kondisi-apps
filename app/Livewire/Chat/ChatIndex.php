<?php

namespace App\Livewire\Chat;

use App\Events\NewChatMessage;
use App\Events\UserTyping;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Traits\HasDynamicLike;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

class ChatIndex extends Component
{
    use AuthorizesRequests, HasDynamicLike;

    public ?int $activeChatId = null;

    public string $messageBody = '';

    public string $searchUser = '';

    public string $searchChat = '';

    public bool $showDeleteModal = false;

    public ?int $deleteChatId = null;

    public ?int $replyToId = null;

    public ?int $editingMessageId = null;

    public string $editMessageBody = '';

    public bool $showDeleteMessageModal = false;

    public ?int $deleteMessageId = null;

    /** @var array Cached chat IDs for current user — avoids repeated whereHas queries */
    private ?array $userChatIds = null;

    public function mount(?int $chat = null): void
    {
        $this->authorize('viewAny', Chat::class);

        if ($chat && $this->isUserChat($chat)) {
            $this->activeChatId = $chat;
            $this->markChatAsRead();
        }
    }

    public function selectChat(int $chatId): void
    {
        if (! $this->isUserChat($chatId)) {
            return;
        }

        $this->activeChatId = $chatId;
        $this->markChatAsRead();
        $this->messageBody = '';
        $this->replyToId = null;
    }

    public function setReply(int $messageId): void
    {
        $this->replyToId = $messageId;
    }

    public function clearReply(): void
    {
        $this->replyToId = null;
    }

    public function editMessage(int $messageId): void
    {
        $message = ChatMessage::find($messageId);

        if (! $message || $message->is_deleted) {
            return;
        }

        $this->authorize('editMessage', $message);

        $this->editingMessageId = $messageId;
        $this->editMessageBody = $message->body;
    }

    public function cancelEdit(): void
    {
        $this->editingMessageId = null;
        $this->editMessageBody = '';
    }

    public function updateMessage(): void
    {
        $body = trim($this->editMessageBody);

        if (empty($body) || ! $this->editingMessageId) {
            return;
        }

        $message = ChatMessage::find($this->editingMessageId);

        if (! $message || $message->is_deleted) {
            $this->cancelEdit();

            return;
        }

        $this->authorize('editMessage', $message);

        // Only mark as edited if body actually changed
        if ($message->body !== $body) {
            $message->update([
                'body' => $body,
                'is_edited' => true,
            ]);
        }

        $this->cancelEdit();
    }

    public function confirmDeleteMessage(int $messageId): void
    {
        $this->deleteMessageId = $messageId;
        $this->showDeleteMessageModal = true;
    }

    public function deleteMessage(): void
    {
        if (! $this->deleteMessageId) {
            return;
        }

        $message = ChatMessage::find($this->deleteMessageId);

        if (! $message) {
            $this->showDeleteMessageModal = false;
            $this->deleteMessageId = null;

            return;
        }

        $this->authorize('deleteMessage', $message);

        // Soft-delete: clear body, mark as deleted (WhatsApp style)
        $message->update([
            'body' => '',
            'is_deleted' => true,
            'reply_to_id' => null,
        ]);

        $this->showDeleteMessageModal = false;
        $this->deleteMessageId = null;
    }

    public function startDirectChat(int $userId): void
    {
        $me = Auth::id();

        if ($userId === $me) {
            return;
        }

        $chat = Chat::findDirectChat($me, $userId);

        if (! $chat) {
            $chat = Chat::create([
                'is_group' => false,
                'created_by' => $me,
            ]);
            $chat->participants()->attach([$me, $userId]);
            $this->userChatIds = null; // bust cache
        } else {
            // Re-opening a previously deleted chat: set deleted_at to now() for a fresh start
            // Only new messages (after this point) will be visible to the user who deleted
            DB::table('chat_participants')
                ->where('chat_id', $chat->id)
                ->where('user_id', $me)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => now()]);
            $this->userChatIds = null; // bust cache
        }

        $this->activeChatId = $chat->id;
        $this->searchUser = '';
        $this->markChatAsRead();
    }

    public function sendMessage(): void
    {
        $body = trim($this->messageBody);

        if (empty($body) || ! $this->activeChatId) {
            return;
        }

        if (! $this->isUserChat($this->activeChatId)) {
            return;
        }

        $message = ChatMessage::create([
            'chat_id' => $this->activeChatId,
            'user_id' => Auth::id(),
            'reply_to_id' => $this->replyToId,
            'body' => $body,
            'type' => 'text',
        ]);

        // Direct DB update — no need to load pivot model
        DB::table('chat_participants')
            ->where('chat_id', $this->activeChatId)
            ->where('user_id', Auth::id())
            ->update(['last_read_at' => now()]);

        try {
            broadcast(new NewChatMessage($message->load('user')))->toOthers();
        } catch (Throwable) {
            // Broadcast failed — message is still saved in DB
        }

        $this->messageBody = '';
        $this->replyToId = null;
    }

    public function sendTyping(bool $isTyping = true): void
    {
        if (! $this->activeChatId) {
            return;
        }

        try {
            broadcast(new UserTyping(
                chatId: $this->activeChatId,
                userId: Auth::id(),
                userName: Auth::user()->name,
                isTyping: $isTyping,
            ))->toOthers();
        } catch (Throwable) {
            // Silently ignore — typing indicator is non-critical
        }
    }

    public function markChatAsRead(): void
    {
        if (! $this->activeChatId) {
            return;
        }

        // Direct DB update — avoids loading Chat model + pivot
        DB::table('chat_participants')
            ->where('chat_id', $this->activeChatId)
            ->where('user_id', Auth::id())
            ->update(['last_read_at' => now()]);
    }

    public function confirmDeleteChat(int $chatId): void
    {
        $this->deleteChatId = $chatId;
        $this->showDeleteModal = true;
    }

    public function deleteChat(): void
    {
        if (! $this->deleteChatId) {
            return;
        }

        if (! $this->isUserChat($this->deleteChatId)) {
            $this->showDeleteModal = false;
            $this->deleteChatId = null;

            return;
        }

        $chat = Chat::findOrFail($this->deleteChatId);
        $this->authorize('delete', $chat);

        // Per-user soft delete: mark deleted_at on current user's pivot only
        // Messages before this timestamp will be hidden from this user
        DB::table('chat_participants')
            ->where('chat_id', $this->deleteChatId)
            ->where('user_id', Auth::id())
            ->update(['deleted_at' => now()]);

        if ($this->activeChatId === $this->deleteChatId) {
            $this->activeChatId = null;
        }

        $this->userChatIds = null;
        $this->showDeleteModal = false;
        $this->deleteChatId = null;
    }

    public function getListeners(): array
    {
        $userId = Auth::id();

        // Use a single user-scoped channel instead of per-chat channels
        // This avoids a DB query on every Livewire request
        return [
            "echo-private:user.{$userId},NewChatMessage" => 'handleNewMessage',
            "echo-private:user.{$userId},UserTyping" => 'handleTyping',
            "echo-private:user.{$userId},NewNotification" => '$refresh',
        ];
    }

    public function handleNewMessage($event): void
    {
        if (isset($event['chat_id']) && $event['chat_id'] == $this->activeChatId) {
            $this->markChatAsRead();
        }
    }

    public function handleTyping($event): void
    {
        $this->dispatch('user-typing', userId: $event['user_id'], userName: $event['user_name'], isTyping: $event['is_typing']);
    }

    /**
     * Check if a chat belongs to the current user (cached per request).
     */
    private function isUserChat(int $chatId): bool
    {
        if ($this->userChatIds === null) {
            $this->userChatIds = DB::table('chat_participants')
                ->where('user_id', Auth::id())
                ->pluck('chat_id')
                ->all();
        }

        return in_array($chatId, $this->userChatIds);
    }

    public function render()
    {
        $userId = Auth::id();

        // Get all chat participant data for current user (including soft-deleted)
        $participantData = DB::table('chat_participants')
            ->where('user_id', $userId)
            ->get(['chat_id', 'last_read_at', 'deleted_at']);

        $lastReadMap = $participantData->pluck('last_read_at', 'chat_id');
        $deletedAtMap = $participantData->pluck('deleted_at', 'chat_id');

        // Load chats with relationships
        $chats = Chat::whereIn('id', $participantData->pluck('chat_id'))
            ->with(['participants', 'latestMessage.user'])
            ->get()
            ->filter(function ($chat) use ($deletedAtMap) {
                $deletedAt = $deletedAtMap[$chat->id] ?? null;
                if (! $deletedAt) {
                    return true;
                }

                // Show chat only if there are messages after deletion
                return $chat->latestMessage && $chat->latestMessage->created_at > Carbon::parse($deletedAt);
            })
            ->sortByDesc(fn ($chat) => $chat->latestMessage?->created_at ?? $chat->created_at);

        // Unread counts: messages after last_read_at AND after deleted_at (if set)
        $chatIds = $chats->pluck('id');
        $unreadCounts = [];
        if ($chatIds->isNotEmpty()) {
            $unreadQuery = DB::table('chat_messages')
                ->select('chat_id', DB::raw('COUNT(*) as unread'))
                ->whereIn('chat_id', $chatIds)
                ->where('user_id', '!=', $userId);

            $unreadQuery->where(function ($q) use ($chatIds, $lastReadMap, $deletedAtMap) {
                foreach ($chatIds as $chatId) {
                    $lastRead = $lastReadMap[$chatId] ?? null;
                    $deletedAt = $deletedAtMap[$chatId] ?? null;

                    $q->orWhere(function ($sub) use ($chatId, $lastRead, $deletedAt) {
                        $sub->where('chat_id', $chatId);
                        if ($lastRead) {
                            $sub->where('created_at', '>', $lastRead);
                        }
                        if ($deletedAt) {
                            $sub->where('created_at', '>', $deletedAt);
                        }
                    });
                }
            });

            $unreadCounts = $unreadQuery->groupBy('chat_id')
                ->pluck('unread', 'chat_id')
                ->all();
        }

        if ($this->searchChat) {
            $chats = $chats->filter(function ($chat) {
                return str_contains(
                    strtolower($chat->display_name),
                    strtolower($this->searchChat)
                );
            });
        }

        $activeChat = null;
        $messages = collect();

        if ($this->activeChatId) {
            $participant = $participantData->firstWhere('chat_id', $this->activeChatId);

            if ($participant) {
                $activeChat = $chats->firstWhere('id', $this->activeChatId);

                // If not in filtered list (e.g., just re-opened a deleted chat), load directly
                if (! $activeChat) {
                    $activeChat = Chat::with(['participants', 'latestMessage.user'])
                        ->find($this->activeChatId);
                }

                if ($activeChat) {
                    $messages = ChatMessage::where('chat_id', $this->activeChatId)
                        ->with(['user', 'replyTo.user'])
                        ->when($participant->deleted_at, fn ($q) => $q->where('created_at', '>', $participant->deleted_at))
                        ->orderBy('created_at', 'asc')
                        ->limit(100)
                        ->get();
                }
            }
        }

        $searchResults = collect();
        if (strlen($this->searchUser) >= 2) {
            $operator = $this->getLikeOperator();
            $searchResults = User::active()
                ->where('id', '!=', $userId)
                ->where(function ($q) use ($operator) {
                    $q->where('name', $operator, "%{$this->searchUser}%")
                        ->orWhere('email', $operator, "%{$this->searchUser}%");
                })
                ->limit(10)
                ->get();
        }

        return view('livewire.chat.chat-index', [
            'chats' => $chats,
            'activeChat' => $activeChat,
            'messages' => $messages,
            'searchResults' => $searchResults,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
