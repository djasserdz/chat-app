<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        return true; // Allow users to list conversations they are part of
    }

    /**
     * Determine whether the user can view a specific conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create a conversation.
     */
    public function create(User $user): bool
    {
        return true; // Allow users to create conversations
    }

    /**
     * Determine whether the user can update a conversation.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can delete a conversation.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return false; // Restrict conversation deletion
    }

    /**
     * Determine whether the user can restore a conversation.
     */
    public function restore(User $user, Conversation $conversation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete a conversation.
     */
    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return false;
    }
}
