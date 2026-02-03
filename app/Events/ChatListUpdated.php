<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatListUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, SerializesModels;

    public string $queue = 'broadcasts';

    public int $userId;
    public array $room;

    public function __construct(int $userId, array $room)
    {
        $this->userId = $userId;
        $this->room = $room;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'chatlist.updated';
    }
}
