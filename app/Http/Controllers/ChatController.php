<?php

namespace App\Http\Controllers;

use App\Events\ChatClosed;
use App\Events\ChatListUpdated;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\ReactionUpdated;
use App\Events\SeenUpdated;
use App\Events\TypingUpdated;
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
        if (!in_array((int)auth()->id(), [(int)$room->user_one, (int)$room->user_two], true)) {
            abort(403);
        }
    }

    private function ensureNotClosed(ChatRoom $room): void
    {
        if ($room->closed_at) {
            abort(403, 'Chat is closed');
        }
    }

    private function otherUserId(ChatRoom $room, int $me): int
    {
        return ((int)$room->user_one === (int)$me) ? (int)$room->user_two : (int)$room->user_one;
    }

    /**
     * SPA-safe: this endpoint should never return HTML.
     */
    public function show(ChatRoom $room)
    {
        $this->ensureParticipant($room);

        $me = (int)auth()->id();

        // Mark other user's messages as read when opening the chat
        $lastReadId = Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', $me)
            ->whereNull('read_at')
            ->max('id');

        if ($lastReadId) {
            Message::where('chat_room_id', $room->id)
                ->where('user_id', '!=', $me)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            // Notify the other user that messages were seen
            broadcast(new SeenUpdated($room->uuid, $me, (int)$lastReadId, now()->toISOString()))->toOthers();
        }

        // Update unread counts for both users in real-time
        $this->broadcastChatListForBoth($room);

        $otherId = $this->otherUserId($room, $me);
        $other = \App\Models\User::select('id', 'name')->find($otherId);

        return response()->json([
            'ok' => true,
            'room' => [
                'id' => $room->id,
                'uuid' => $room->uuid,
                'other_user' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
                'closed_at' => optional($room->closed_at)->toISOString(),
                'closed_by' => $room->closed_by,
            ],
        ]);
    }

    public function send(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);
        $this->ensureNotClosed($room);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        $me = (int)auth()->id();

        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId) {
            $exists = Message::where('chat_room_id', $room->id)->where('id', $replyToId)->exists();
            if (!$exists) {
                abort(422, 'Invalid reply target');
            }
        }

        $msg = $room->messages()->create([
            'user_id' => $me,
            'body' => $data['body'],
            'reply_to_id' => $replyToId,
        ]);

        $msg = $msg->fresh(['replyTo:id,user_id,body,deleted_at,created_at']);
        $serialized = $this->serializeMessage($msg, $me);

        $this->updateReplyStats($room, $msg);

        // Realtime message for the other participant
        broadcast(new MessageSent($room->uuid, $serialized))->toOthers();

        // Update chat list + unread counts for both participants
        $this->broadcastChatListForBoth($room);

        return response()->json([
            'ok' => true,
            'message' => $serialized,
        ]);
    }

    public function messages(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);

        $me = (int)auth()->id();

        // Mark OTHER user's unread messages as read whenever this endpoint is hit
        $lastReadId = Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', $me)
            ->whereNull('read_at')
            ->max('id');

        if ($lastReadId) {
            Message::where('chat_room_id', $room->id)
                ->where('user_id', '!=', $me)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            broadcast(new SeenUpdated($room->uuid, $me, (int)$lastReadId, now()->toISOString()))->toOthers();
            $this->broadcastChatListForBoth($room);
        }

        $limit = (int)($request->get('limit', 30));
        $limit = max(10, min($limit, 60));

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
                'items' => $items->map(fn($m) => $this->serializeMessage($m, $me))->values(),
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
            'items' => $itemsAsc->map(fn($m) => $this->serializeMessage($m, $me))->values(),
            'next_before_id' => $itemsDesc->count() ? $itemsDesc->last()->id : null,
            'has_more' => $itemsDesc->count() === $limit,
            'chat_closed_at' => optional($room->closed_at)->toISOString(),
            'chat_closed_by' => $room->closed_by,
        ]);
    }

    public function typing(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);

        $me = (int)auth()->id();
        $typing = (bool)$request->boolean('typing');
        $until = $typing ? now()->addSeconds(2) : now();

        if ((int)$room->user_one === $me) {
            $room->user_one_typing_until = $until;
        } else {
            $room->user_two_typing_until = $until;
        }
        $room->save();

        \Log::info('Broadcast debug', [
            'default' => config('broadcasting.default'),
            'reverb'  => config('broadcasting.connections.reverb'),
            'pusher'  => config('broadcasting.connections.pusher'),
        ]);

        broadcast(new TypingUpdated($room->uuid, $me, $typing))->toOthers();

        // Let the other user update chat-list typing indicator
        $this->broadcastChatListForBoth($room);

        return response()->json(['ok' => true]);
    }

    public function typingStatus(ChatRoom $room)
    {
        $this->ensureParticipant($room);
        $me = (int)auth()->id();

        $otherTypingUntil = ((int)$room->user_one === $me)
            ? $room->user_two_typing_until
            : $room->user_one_typing_until;

        return response()->json([
            'ok' => true,
            'typing' => $otherTypingUntil ? $otherTypingUntil->isFuture() : false,
        ]);
    }

    public function seen(Request $request, ChatRoom $room)
    {
        // Explicit endpoint (optional) to mark messages seen
        $this->ensureParticipant($room);

        $me = (int)auth()->id();
        $lastReadId = Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', $me)
            ->whereNull('read_at')
            ->max('id');

        if ($lastReadId) {
            Message::where('chat_room_id', $room->id)
                ->where('user_id', '!=', $me)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            broadcast(new SeenUpdated($room->uuid, $me, (int)$lastReadId, now()->toISOString()))->toOthers();
            $this->broadcastChatListForBoth($room);
        }

        return response()->json(['ok' => true]);
    }

    public function seenStatus(ChatRoom $room)
    {
        $this->ensureParticipant($room);

        // For the current viewer: their latest read message is not very meaningful.
        // We'll return last read_at for the room.
        $last = Message::where('chat_room_id', $room->id)
            ->whereNotNull('read_at')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'ok' => true,
            'message_id' => $last?->id,
            'read_at' => optional($last?->read_at)->toISOString(),
        ]);
    }

    public function toggleHeart(Request $request, ChatRoom $room, Message $message)
    {
        $this->ensureParticipant($room);

        if ((int)$message->chat_room_id !== (int)$room->id) {
            abort(404);
        }

        $me = (int)auth()->id();

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

        broadcast(new ReactionUpdated($room->uuid, (int)$message->id, (int)$out['heart_count'], $me, (bool)$out['hearted']))->toOthers();

        return response()->json(['ok' => true] + $out);
    }

    public function deleteMessage(Request $request, ChatRoom $room, Message $message)
    {
        $this->ensureParticipant($room);

        if ((int)$message->chat_room_id !== (int)$room->id) {
            abort(404);
        }

        $me = (int)auth()->id();

        $message->update([
            'body' => null,
            'deleted_at' => now(),
            'deleted_by' => $me,
        ]);

        broadcast(new MessageDeleted($room->uuid, (int)$message->id))->toOthers();
        $this->broadcastChatListForBoth($room);

        return response()->json(['ok' => true]);
    }

    public function deleteChat(Request $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);

        if (!$room->closed_at) {
            $room->update([
                'closed_at' => now(),
                'closed_by' => (int)auth()->id(),
            ]);

            broadcast(new ChatClosed($room->uuid, optional($room->closed_at)->toISOString(), (int)auth()->id()))->toOthers();
            $this->broadcastChatListForBoth($room);
        }

        return response()->json(['ok' => true]);
    }

    private function serializeMessage(Message $m, int $me): array
    {
        // If message is deleted, we keep body null so UI can show a placeholder.
        $reply = null;
        if ($m->relationLoaded('replyTo') && $m->replyTo) {
            $reply = [
                'id' => $m->replyTo->id,
                'user_id' => $m->replyTo->user_id,
                'body' => $m->replyTo->body,
                'deleted_at' => optional($m->replyTo->deleted_at)->toISOString(),
                'created_at' => optional($m->replyTo->created_at)->toISOString(),
            ];
        }

        return [
            'id' => $m->id,
            'chat_room_id' => $m->chat_room_id,
            'user_id' => $m->user_id,
            'body' => $m->body,
            'reply_to_id' => $m->reply_to_id,
            'reply_to' => $reply,
            'heart_count' => (int)($m->heart_count ?? 0),
            'my_hearted' => (bool)($m->my_hearted ?? false),
            'created_at' => optional($m->created_at)->toISOString(),
            'read_at' => optional($m->read_at)->toISOString(),
            'deleted_at' => optional($m->deleted_at)->toISOString(),
        ];
    }

    private function updateReplyStats(ChatRoom $room, Message $msg): void
    {
        // Lightweight placeholder: keep existing stats table populated.
        // You can extend this later for analytics.
        try {
            UserChatStat::firstOrCreate(['user_id' => $msg->user_id], [
                'reply_time_ema_sec' => 0,
                'replies_count' => 0,
                'reply_within_1h' => 0,
                'reply_within_24h' => 0,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function broadcastChatListForBoth(ChatRoom $room): void
    {
        $u1 = (int)$room->user_one;
        $u2 = (int)$room->user_two;

        // For now, the client simply re-fetches /api/bootstrap on chatlist.updated.
        // Still include minimal payload for future diff-patching.
        broadcast(new ChatListUpdated($u1, ['room_uuid' => $room->uuid]))->toOthers();
        broadcast(new ChatListUpdated($u2, ['room_uuid' => $room->uuid]))->toOthers();

        // NOTE: `toOthers()` means the user triggering the action won't receive this.
        // That's okay: the SPA updates optimistically + will refresh on next event.
    }
}
