<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('chat.room.{roomUuid}', function ($user, $roomUuid) {
    // Cache room participants for short periods to avoid DB spikes during reconnect storms.
    $room = Cache::remember("room_auth:{$roomUuid}", now()->addSeconds(60), function () use ($roomUuid) {
        return \App\Models\ChatRoom::select('user_one', 'user_two')->where('uuid', $roomUuid)->first();
    });

    if (!$room) return false;

    return in_array((int)$user->id, [(int)$room->user_one, (int)$room->user_two], true);
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int)$user->id === (int)$userId;
});
