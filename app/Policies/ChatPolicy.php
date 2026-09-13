<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;

class ChatPolicy
{
    /**
     * Determine whether the user can view the chat module.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('chat_view');
    }

    /**
     * Determine whether the user can create a new chat.
     */
    public function create(User $user): bool
    {
        return $user->can('chat_create');
    }

    /**
     * Determine whether the user can delete a chat (per-user soft delete).
     * Only participants with chat_delete permission can delete.
     */
    public function delete(User $user, Chat $chat): bool
    {
        return $user->can('chat_delete')
            && $chat->participants()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can edit a chat message.
     * Users can only edit their own messages.
     */
    public function editMessage(User $user, ChatMessage $message): bool
    {
        return $user->can('chat_view') && $message->user_id === $user->id && ! $message->is_deleted;
    }

    /**
     * Determine whether the user can delete a chat message.
     * Users can only delete their own messages.
     */
    public function deleteMessage(User $user, ChatMessage $message): bool
    {
        return $user->can('chat_view') && $message->user_id === $user->id && ! $message->is_deleted;
    }
}
