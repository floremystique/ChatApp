<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function delete(User $user, Message $message): bool
    {
        // "Delete my own message" by default (safer).
        // If you later want "delete for everyone", implement a separate ability.
        return (int)$message->user_id === (int)$user->id;
    }

    public function react(User $user, Message $message): bool
    {
        // Only participants can react; enforced at route-level by room participant checks.
        return $user->exists;
    }
}
