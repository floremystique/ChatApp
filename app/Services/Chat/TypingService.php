<?php

namespace App\Services\Chat;

use App\Models\ChatRoom;
use Illuminate\Support\Facades\Cache;

class TypingService
{
    public function key(string $roomUuid, int $userId): string
    {
        return "typing:{$roomUuid}:{$userId}";
    }

    public function setTyping(ChatRoom $room, int $userId, bool $isTyping, int $ttlSeconds = 3): void
    {
        $k = $this->key((string)$room->uuid, $userId);

        if ($isTyping) {
            Cache::put($k, 1, now()->addSeconds($ttlSeconds));
        } else {
            Cache::forget($k);
        }
    }

    public function isTyping(ChatRoom $room, int $userId): bool
    {
        return Cache::has($this->key((string)$room->uuid, $userId));
    }

    public function statusForRoom(ChatRoom $room, int $meId): array
    {
        $other = ((int)$room->user_one === (int)$meId) ? (int)$room->user_two : (int)$room->user_one;

        return [
            'me'    => $this->isTyping($room, $meId),
            'other' => $this->isTyping($room, $other),
            'other_user_id' => $other,
        ];
    }
}
