<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $roomUuid;
    public int $messageId;

    public function __construct(string $roomUuid, int $messageId)
    {
        $this->roomUuid = $roomUuid;
        $this->messageId = $messageId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.room.' . $this->roomUuid);
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }
}
