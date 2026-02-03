<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, SerializesModels;

    public string $queue = 'broadcasts';

    public string $roomUuid;
    public int $messageId;
    public int $heartCount;
    public int $userId;
    public bool $hearted;

    public function __construct(string $roomUuid, int $messageId, int $heartCount, int $userId, bool $hearted)
    {
        $this->roomUuid = $roomUuid;
        $this->messageId = $messageId;
        $this->heartCount = $heartCount;
        $this->userId = $userId;
        $this->hearted = $hearted;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.room.' . $this->roomUuid);
    }

    public function broadcastAs(): string
    {
        return 'reaction.updated';
    }
}
