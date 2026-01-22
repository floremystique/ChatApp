<?php

use Illuminate\Support\Facades\Broadcast;

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
    $room = \App\Models\ChatRoom::where('uuid', $roomUuid)->first();
    if (!$room) return false;
    return in_array((int)$user->id, [(int)$room->user_one, (int)$room->user_two]);
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int)$user->id === (int)$userId;
});
