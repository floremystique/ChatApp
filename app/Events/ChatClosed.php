<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatClosed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $roomUuid;
    public string $closedAt;
    public int $closedBy;

    public function __construct(string $roomUuid, string $closedAt, int $closedBy)
    {
        $this->roomUuid = $roomUuid;
        $this->closedAt = $closedAt;
        $this->closedBy = $closedBy;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.room.' . $this->roomUuid);
    }

    public function broadcastAs(): string
    {
        return 'chat.closed';
    }
}
