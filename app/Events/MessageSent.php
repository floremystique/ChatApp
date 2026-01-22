<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $roomUuid;
    public array $message;

    public function __construct(string $roomUuid, array $message)
    {
        $this->roomUuid = $roomUuid;
        $this->message = $message;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.room.' . $this->roomUuid);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
