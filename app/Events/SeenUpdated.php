<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeenUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, SerializesModels;

    public string $queue = 'broadcasts';

    public string $roomUuid;
    public int $userId;
    public ?int $messageId;
    public ?string $readAt;

    public function __construct(string $roomUuid, int $userId, ?int $messageId, ?string $readAt)
    {
        $this->roomUuid = $roomUuid;
        $this->userId = $userId;
        $this->messageId = $messageId;
        $this->readAt = $readAt;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.room.' . $this->roomUuid);
    }

    public function broadcastAs(): string
    {
        // SPA listens for `.message.seen`
        return 'message.seen';
    }
}
