<?php

namespace App\Services\Chat;

use App\Models\ChatRoom;
use Illuminate\Support\Facades\DB;

class ReadStateService
{
    public function ensureRow(int $roomId, int $userId): void
    {
        DB::table('chat_room_reads')->updateOrInsert(
            ['chat_room_id' => $roomId, 'user_id' => $userId],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    public function lastReadId(int $roomId, int $userId): ?int
    {
        $v = DB::table('chat_room_reads')
            ->where('chat_room_id', $roomId)
            ->where('user_id', $userId)
            ->value('last_read_message_id');

        return $v ? (int)$v : null;
    }

    /**
     * Advances read pointer + resets unread count ONLY if pointer moves forward.
     * Returns true if an update happened.
     */
    public function markReadUpTo(ChatRoom $room, int $userId, ?int $messageId): bool
    {
        $this->ensureRow((int)$room->id, $userId);

        if (!$messageId) {
            return false;
        }

        $current = $this->lastReadId((int)$room->id, $userId);

        if ($current !== null && $current >= (int)$messageId) {
            return false;
        }

        DB::table('chat_room_reads')
            ->where('chat_room_id', $room->id)
            ->where('user_id', $userId)
            ->update([
                'last_read_message_id' => (int)$messageId,
                'unread_count' => 0,
                'updated_at' => now(),
            ]);

        return true;
    }

    public function unreadCount(int $roomId, int $userId): int
    {
        $v = DB::table('chat_room_reads')
            ->where('chat_room_id', $roomId)
            ->where('user_id', $userId)
            ->value('unread_count');

        return $v ? (int)$v : 0;
    }
}
