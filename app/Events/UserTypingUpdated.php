<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTypingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /** The user who should receive this typing update (channel target). */
    public int $toUserId;

    public string $roomUuid;
    public int $userId;
    public bool $typing;

    public function __construct(int $toUserId, string $roomUuid, int $userId, bool $typing)
    {
        $this->toUserId = $toUserId;
        $this->roomUuid = $roomUuid;
        $this->userId = $userId;
        $this->typing = $typing;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->toUserId);
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'roomUuid' => $this->roomUuid,
            'userId'   => $this->userId,
            'typing'   => $this->typing,
        ];
    }
}
