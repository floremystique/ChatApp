<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatListUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

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
