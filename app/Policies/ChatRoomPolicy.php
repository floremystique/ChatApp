<?php

namespace App\Policies;

use App\Models\ChatRoom;
use App\Models\User;

class ChatRoomPolicy
{
    public function view(User $user, ChatRoom $room): bool
    {
        return in_array((int)$user->id, [(int)$room->user_one, (int)$room->user_two], true);
    }

    public function send(User $user, ChatRoom $room): bool
    {
        if (!$this->view($user, $room)) {
            return false;
        }
        return $room->closed_at === null;
    }
}
