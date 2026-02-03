<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, SerializesModels;

    public string $queue = 'broadcasts';

    public string $roomUuid;
    public int $userId;
    public bool $typing;

    public function __construct(string $roomUuid, int $userId, bool $typing)
    {
        $this->roomUuid = $roomUuid;
        $this->userId = $userId;
        $this->typing = $typing;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.room.' . $this->roomUuid);
    }

    public function broadcastAs(): string
    {
        return 'typing.updated';
    }
}
