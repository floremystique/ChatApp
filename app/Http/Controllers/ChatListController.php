<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use Illuminate\Http\Request;

class ChatListController extends Controller
{
    public function poll(Request $request)
    {
        $me = auth()->id();

        $rooms = \App\Models\ChatRoom::query()
        ->where('user_one', auth()->id())
        ->orWhere('user_two', auth()->id())
        ->with(['lastMessage', 'userOne', 'userTwo'])
        ->get();


        $data = $rooms->map(function ($r) use ($me) {
            $other = ($r->user_one == $me) ? $r->userTwo : $r->userOne;

            // unread count (assuming you have messages.read_at)
            $unread = $r->messages()
                ->whereNull('read_at')
                ->where('user_id', '!=', $me)
                ->count();

            // typing (if you added typing_until fields)
            $otherTypingUntil = ($r->user_one == $me) ? $r->user_two_typing_until : $r->user_one_typing_until;
            $typing = $otherTypingUntil && now()->lt($otherTypingUntil);

            return [
                'room_uuid' => $r->uuid,
                'other_name' => $other?->name ?? 'Unknown',
                'last_body' => $r->lastMessage?->body ?? '',
                'last_at' => optional($r->lastMessage?->created_at)->toISOString(),
                'unread' => $unread,
                'typing' => $typing,
            ];
        });

        return response()->json($data);
    }
}
