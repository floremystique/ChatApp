<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestBroadcastNow implements ShouldBroadcast, ShouldQueue
{
    public string $queue = 'broadcasts';

    public function broadcastOn()
    {
        return new Channel('test-channel');
    }

    public function broadcastAs()
    {
        return 'test.event';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'Railway broadcast works 🚀',
            'ts' => now()->toIso8601String(),
        ];
    }
}
