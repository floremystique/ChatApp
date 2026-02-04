<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Cache;

class ChatListCacheService
{
    public function keyForUser(int $userId): string
    {
        return "rooms:$userId";
    }

    public function forgetForUsers(int $userA, int $userB): void
    {
        Cache::forget($this->keyForUser($userA));
        Cache::forget($this->keyForUser($userB));
    }
}
