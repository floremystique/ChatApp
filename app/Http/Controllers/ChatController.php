<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChatRoom;
use App\Models\Message;
use Carbon\Carbon;

class ChatController extends Controller
{
    private function ensureParticipant(ChatRoom $room): void
    {
        if (!in_array(auth()->id(), [$room->user_one, $room->user_two])) {
            abort(403);
        }
    }

    public function index()
    {
        $me = auth()->id();

        $rooms = \App\Models\ChatRoom::query()
            ->where('user_one', $me)
            ->orWhere('user_two', $me)
            ->with(['lastMessage'])
            ->orderByDesc(
                \App\Models\Message::select('created_at')
                    ->whereColumn('chat_rooms.id', 'messages.chat_room_id')
                    ->latest()
                    ->take(1)
            )
            ->get()
            ->map(function ($room) use ($me) {
                $otherId = ($room->user_one == $me) ? $room->user_two : $room->user_one;
                $room->other_user = \App\Models\User::find($otherId);

                $room->unread_count = \App\Models\Message::where('chat_room_id', $room->id)
                    ->whereNull('read_at')
                    ->where('user_id', '!=', $me)
                    ->count();

                return $room;
            });

        return view('chat.index', compact('rooms'));
    }


    public function show(ChatRoom $room)
    {
        abort_unless($room->user_one === auth()->id() || $room->user_two === auth()->id(), 403);

        // mark other user's messages as read
        Message::where('chat_room_id', $room->id)
        ->where('user_id', '!=', auth()->id())
        ->whereNull('read_at')
        ->update(['read_at' => now()]);


        return view('chat.show', compact('room'));
    }


    public function send(Request $request, ChatRoom $room)
    {
        $data = $request->validate([
            'body' => ['required','string','max:10000'],
        ]);

        $msg = $room->messages()->create([
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'body' => $msg->body,
                'created_at' => $msg->created_at,
                'read_at' => $msg->read_at,
            ],
        ]);
    }


    public function messages(Request $request, ChatRoom $room)
    {

        abort_unless($room->user_one === auth()->id() || $room->user_two === auth()->id(), 403);

        // ✅ mark OTHER user's unread messages as read whenever this endpoint is hit
        Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // TODO: ensure auth user belongs to the room (you likely already do)
        $limit = (int)($request->get('limit', 30));
        $limit = max(10, min($limit, 60)); // safety

        $afterId = $request->get('after_id'); // load newer than this id
        
        if ($afterId) {
            $items = Message::where('chat_room_id', $room->id)
                ->where('id', '>', (int)$afterId)
                ->orderBy('id')
                ->take($limit)
                ->get(['id','chat_room_id','user_id','body','created_at','read_at']);

            return response()->json([
                'items' => $items,
                'next_before_id' => null,
                'has_more' => false,
            ]);
        }

        $beforeId = $request->get('before_id'); // load older than this id

        $q = Message::query()
            ->where('chat_room_id', $room->id)
            ->orderByDesc('id');

        if ($beforeId) {
            $q->where('id', '<', (int)$beforeId);
        }

        $items = $q->take($limit)->get();

        // return oldest->newest for rendering
        $itemsAsc = $items->reverse()->values();

        return response()->json([
            'items' => $itemsAsc,
            'next_before_id' => $items->count() ? $items->last()->id : null, // last() here = oldest in DESC set
            'has_more' => $items->count() === $limit,
        ]);
    }

    public function typing(Request $request, \App\Models\ChatRoom $room)
    {
        $request->validate(['typing' => 'required|boolean']);

        $me = auth()->id();
        $until = $request->boolean('typing') ? now()->addSeconds(3) : null;

        if ($room->user_one == $me) {
            $room->user_one_typing_until = $until;
        } elseif ($room->user_two == $me) {
            $room->user_two_typing_until = $until;
        } else {
            abort(403);
        }

        $room->save();

        return response()->json(['ok' => true]);
    }

    public function typingStatus(\App\Models\ChatRoom $room)
    {
        $me = auth()->id();

        if (!in_array($me, [$room->user_one, $room->user_two])) {
            abort(403);
        }

        $otherTypingUntil = ($room->user_one == $me)
            ? $room->user_two_typing_until
            : $room->user_one_typing_until;

        $isTyping = $otherTypingUntil && now()->lt($otherTypingUntil);

        return response()->json(['typing' => $isTyping]);
    }



    public function seenStatus(ChatRoom $room)
    {
        abort_unless($room->user_one === auth()->id() || $room->user_two === auth()->id(), 403);

        $lastMine = Message::where('chat_room_id', $room->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first(['id','read_at']);

        return response()->json([
            'id' => $lastMine?->id,
            'read_at' => optional($lastMine?->read_at)->toISOString(),
        ]);
    }
}
