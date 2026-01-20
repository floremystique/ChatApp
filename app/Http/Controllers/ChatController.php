<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\UserChatStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    private function ensureParticipant(ChatRoom $room): void
    {
        if (!in_array(auth()->id(), [$room->user_one, $room->user_two])) {
            abort(403);
        }
    }

    private function ensureNotClosed(ChatRoom $room): void
    {
        if ($room->closed_at) {
            abort(403, 'Chat is closed');
        }
    }

    public function index()
    {
        $me = auth()->id();

        // NOTE: this can be optimized further with joins + subqueries (for huge scale).
        $rooms = ChatRoom::query()
            ->where('user_one', $me)
            ->orWhere('user_two', $me)
            ->with(['lastMessage'])
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('chat_rooms.id', 'messages.chat_room_id')
                    ->latest()
                    ->take(1)
            )
            ->get()
            ->map(function ($room) use ($me) {
                $otherId = ($room->user_one == $me) ? $room->user_two : $room->user_one;
                $room->other_user = \App\Models\User::find($otherId);

                $room->unread_count = Message::where('chat_room_id', $room->id)
                    ->whereNull('read_at')
                    ->where('user_id', '!=', $me)
                    ->count();

                return $room;
            });

        return view('chat.index', compact('rooms'));
    }

    public function show(ChatRoom $room)
    {
        $this->ensureParticipant($room);

        // mark other user's messages as read
        Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('chat.show', compact('room'));
    }

    public function send(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);
        $this->ensureNotClosed($room);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId) {
            $exists = Message::where('chat_room_id', $room->id)->where('id', $replyToId)->exists();
            if (!$exists) {
                abort(422, 'Invalid reply target');
            }
        }

        $msg = $room->messages()->create([
            'user_id' => auth()->id(),
            'body' => $data['body'],
            'reply_to_id' => $replyToId,
        ]);

        $this->updateReplyStats($room, $msg);

        return response()->json([
            'ok' => true,
            'message' => $this->serializeMessage($msg, auth()->id()),
        ]);
    }

    public function messages(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);

        // mark OTHER user's unread messages as read whenever this endpoint is hit
        Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $limit = (int)($request->get('limit', 30));
        $limit = max(10, min($limit, 60));

        $me = auth()->id();

        $afterId = $request->get('after_id');
        if ($afterId) {
            $items = Message::query()
                ->where('chat_room_id', $room->id)
                ->where('id', '>', (int)$afterId)
                ->orderBy('id')
                ->with(['replyTo:id,user_id,body,deleted_at,created_at'])
                ->addSelect([
                    'my_hearted' => MessageReaction::selectRaw('1')
                        ->whereColumn('message_reactions.message_id', 'messages.id')
                        ->where('message_reactions.user_id', $me)
                        ->where('message_reactions.type', 'heart')
                        ->limit(1),
                ])
                ->take($limit)
                ->get();

            return response()->json([
                'items' => $items->map(fn ($m) => $this->serializeMessage($m, $me))->values(),
                'next_before_id' => null,
                'has_more' => false,
                'chat_closed_at' => optional($room->closed_at)->toISOString(),
                'chat_closed_by' => $room->closed_by,
            ]);
        }

        $beforeId = $request->get('before_id');

        $q = Message::query()
            ->where('chat_room_id', $room->id)
            ->orderByDesc('id')
            ->with(['replyTo:id,user_id,body,deleted_at,created_at'])
            ->addSelect([
                'my_hearted' => MessageReaction::selectRaw('1')
                    ->whereColumn('message_reactions.message_id', 'messages.id')
                    ->where('message_reactions.user_id', $me)
                    ->where('message_reactions.type', 'heart')
                    ->limit(1),
            ]);

        if ($beforeId) {
            $q->where('id', '<', (int)$beforeId);
        }

        $itemsDesc = $q->take($limit)->get();
        $itemsAsc = $itemsDesc->reverse()->values();

        return response()->json([
            'items' => $itemsAsc->map(fn ($m) => $this->serializeMessage($m, $me))->values(),
            'next_before_id' => $itemsDesc->count() ? $itemsDesc->last()->id : null,
            'has_more' => $itemsDesc->count() === $limit,
            'chat_closed_at' => optional($room->closed_at)->toISOString(),
            'chat_closed_by' => $room->closed_by,
        ]);
    }

    public function toggleHeart(Request $request, ChatRoom $room, Message $message)
    {
        $this->ensureParticipant($room);

        if ((int)$message->chat_room_id !== (int)$room->id) {
            abort(404);
        }

        $me = auth()->id();

        $out = DB::transaction(function () use ($me, $message) {
            $existing = MessageReaction::where('message_id', $message->id)
                ->where('user_id', $me)
                ->where('type', 'heart')
                ->first();

            if ($existing) {
                $existing->delete();
                Message::whereKey($message->id)->where('heart_count', '>', 0)->decrement('heart_count');
                $hearted = false;
            } else {
                MessageReaction::create([
                    'message_id' => $message->id,
                    'user_id' => $me,
                    'type' => 'heart',
                ]);
                Message::whereKey($message->id)->increment('heart_count');
                $hearted = true;
            }

            $count = (int)Message::whereKey($message->id)->value('heart_count');

            return ['hearted' => $hearted, 'heart_count' => $count];
        });

        return response()->json(['ok' => true] + $out);
    }

    public function deleteMessage(Request $request, ChatRoom $room, Message $message)
    {
        $this->ensureParticipant($room);

        if ((int)$message->chat_room_id !== (int)$room->id) {
            abort(404);
        }

        if ((int)$message->user_id !== (int)auth()->id()) {
            abort(403);
        }

        if (!$message->deleted_at) {
            $message->deleted_at = now();
            $message->deleted_by = auth()->id();
            $message->save();
        }

        return response()->json(['ok' => true, 'id' => $message->id]);
    }

    public function deleteChat(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);

        if (!$room->closed_at) {
            $room->closed_at = now();
            $room->closed_by = auth()->id();
            $room->save();
        }

        return response()->json([
            'ok' => true,
            'closed_at' => optional($room->closed_at)->toISOString(),
            'closed_by' => $room->closed_by,
        ]);
    }

    public function typing(Request $request, ChatRoom $room)
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

    public function typingStatus(ChatRoom $room)
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
        $this->ensureParticipant($room);

        $lastMine = Message::where('chat_room_id', $room->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first(['id', 'read_at']);

        return response()->json([
            'id' => $lastMine?->id,
            'read_at' => optional($lastMine?->read_at)->toISOString(),
        ]);
    }

    private function serializeMessage(Message $msg, int $me): array
    {
        $reply = null;
        if ($msg->reply_to_id && $msg->relationLoaded('replyTo') && $msg->replyTo) {
            $reply = [
                'id' => $msg->replyTo->id,
                'user_id' => $msg->replyTo->user_id,
                'created_at' => optional($msg->replyTo->created_at)->toISOString(),
                'deleted_at' => optional($msg->replyTo->deleted_at)->toISOString(),
                'body_preview' => $msg->replyTo->deleted_at ? null : mb_substr((string)$msg->replyTo->body, 0, 140),
            ];
        }

        return [
            'id' => $msg->id,
            'chat_room_id' => $msg->chat_room_id,
            'user_id' => $msg->user_id,
            'body' => $msg->deleted_at ? null : $msg->body,
            'deleted_at' => optional($msg->deleted_at)->toISOString(),
            'reply_to_id' => $msg->reply_to_id,
            'reply_to' => $reply,
            'heart_count' => (int)($msg->heart_count ?? 0),
            'my_hearted' => (bool)($msg->my_hearted ?? false),
            'created_at' => optional($msg->created_at)->toISOString(),
            'read_at' => optional($msg->read_at)->toISOString(),
        ];
    }

    private function updateReplyStats(ChatRoom $room, Message $newMsg): void
    {
        $me = $newMsg->user_id;

        $lastOther = Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', $me)
            ->latest('id')
            ->first(['id', 'created_at']);

        if (!$lastOther) return;

        $deltaSec = max(0, $newMsg->created_at->diffInSeconds($lastOther->created_at));

        $stat = UserChatStat::firstOrCreate(['user_id' => $me]);

        $alpha = 0.2;
        $old = $stat->reply_time_ema_sec ?: $deltaSec;
        $ema = (int)round($alpha * $deltaSec + (1 - $alpha) * $old);

        $stat->reply_time_ema_sec = $ema;
        $stat->replies_count = $stat->replies_count + 1;

        $n = max(1, (int)$stat->replies_count);

        $within1h = ($deltaSec <= 3600) ? 100 : 0;
        $within24h = ($deltaSec <= 86400) ? 100 : 0;

        $stat->reply_within_1h = (int)round((($stat->reply_within_1h * ($n - 1)) + $within1h) / $n);
        $stat->reply_within_24h = (int)round((($stat->reply_within_24h * ($n - 1)) + $within24h) / $n);

        $stat->save();
    }
}
