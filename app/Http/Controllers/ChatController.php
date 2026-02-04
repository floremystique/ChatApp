<?php

namespace App\Http\Controllers;

use App\Events\ChatClosed;
use App\Events\ChatListUpdated;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\ReactionUpdated;
use App\Events\SeenUpdated;
use App\Events\TypingUpdated;
use App\Events\UserTypingUpdated;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\UserChatStat;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\TypingRequest;
use App\Http\Requests\Chat\SeenRequest;
use App\Services\Chat\TypingService;
use App\Services\Chat\ReadStateService;
use App\Services\Chat\ChatListCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\LockProvider;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct(
        private readonly TypingService $typingService,
        private readonly ReadStateService $readStateService,
        private readonly ChatListCacheService $chatListCacheService,
    ) {}

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
     * Ensure the read-state row exists (cheap, idempotent).
     */
    private function getLastReadId(int $roomId, int $userId): ?int
    {
        $v = DB::table('chat_room_reads')
            ->where('chat_room_id', $roomId)
            ->where('user_id', $userId)
            ->value('last_read_message_id');

        return $v ? (int)$v : null;
    }

    private function markReadUpTo(ChatRoom $room, int $me, ?int $messageId): bool
    {
        $this->readStateService->ensureRow((int)$room->id, $me);

        // messageId can be null when room has no messages.
        if (!$messageId) {
            return false;
        }

        // Only write when advancing the read pointer (prevents useless writes on debounced /seen calls).
        $updated = DB::table('chat_room_reads')
            ->where('chat_room_id', (int)$room->id)
            ->where('user_id', $me)
            ->where(function ($q) use ($messageId) {
                $q->whereNull('last_read_message_id')
                  ->orWhere('last_read_message_id', '<', $messageId);
            })
            ->update([
                'last_read_message_id' => $messageId,
                'unread_count' => 0,
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }


    // NOTE: SPA deep-links like /chat/{uuid} are served by SpaController@index.
    // Keep all chat data access behind explicit JSON APIs (send/messages/seen/typing/etc).

    public function send(SendMessageRequest $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);
        $this->ensureNotClosed($room);

        $data = $request->validated();


        $me = (int)auth()->id();
        $otherId = $this->otherUserId($room, $me);

        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId) {
            $exists = Message::where('chat_room_id', $room->id)->where('id', $replyToId)->exists();
            if (!$exists) {
                abort(422, 'Invalid reply target');
            }
        }

        $clientMessageId = $data['client_message_id'] ?? Str::uuid()->toString();

        // Idempotency + race-safety:
        // - Mobile clients (Capacitor) can retry sends during offline/online transitions.
        // - We protect with a short cache lock when supported, and we also do a DB lookup by client_message_id.
        $sendFn = function () use ($room, $me, $otherId, $data, $replyToId, $clientMessageId) {
            return DB::transaction(function () use ($room, $me, $otherId, $data, $replyToId, $clientMessageId) {
                // If we've already stored this client message id for this user+room, return it without side-effects.
                $existing = Message::query()
                    ->where('chat_room_id', (int)$room->id)
                    ->where('user_id', $me)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                // Ensure read rows exist
                $this->readStateService->ensureRow((int)$room->id, $me);
                $this->readStateService->ensureRow((int)$room->id, $otherId);

                $m = $room->messages()->create([
                    'user_id' => $me,
                    'client_message_id' => $clientMessageId,
                    'body' => $data['body'],
                    'reply_to_id' => $replyToId,
                ]);

                // Update room pointer for fast chat list
                $room->last_message_id = $m->id;
                $room->updated_at = now();
                $room->save();

                // Sender has read their own message
                DB::table('chat_room_reads')
                    ->where('chat_room_id', (int)$room->id)
                    ->where('user_id', $me)
                    ->update([
                        'last_read_message_id' => $m->id,
                        'unread_count' => 0,
                        'last_seen_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Recipient unread count increments atomically (O(1))
                DB::table('chat_room_reads')
                    ->where('chat_room_id', (int)$room->id)
                    ->where('user_id', $otherId)
                    ->update([
                        'unread_count' => DB::raw('unread_count + 1'),
                        'updated_at' => now(),
                    ]);

                return $m;
            });
        };

        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            $lock = Cache::lock("send:{$room->uuid}:{$me}:{$clientMessageId}", 5);
            $msg = $lock->block(2, $sendFn);
        } else {
            $msg = $sendFn();
        }

        $msg = $msg->fresh(['replyTo:id,user_id,body,deleted_at,created_at']);
        $serialized = $this->serializeMessage($msg, $me);

        $this->updateReplyStats($room, $msg);

        // Sending is a definitive stop-typing signal. Clear server-side typing immediately
        // so the chats list never gets stuck on "Typing..." even if the client misses the stop call.
        $this->typingService->setTyping($room, $me, false);
        broadcast(new TypingUpdated($room->uuid, $me, false))->toOthers();
        broadcast(new UserTypingUpdated($otherId, $room->uuid, $me, false));

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
        $otherId = $this->otherUserId($room, $me);

        $this->readStateService->ensureRow((int)$room->id, $me);
        $this->readStateService->ensureRow((int)$room->id, $otherId);

        // IMPORTANT (perf): Do NOT mark seen automatically on every fetch.
        // The client calls /seen in a debounced way only when the room is actually visible.

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
                'other_last_read_id' => $this->getLastReadId((int)$room->id, $otherId),
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
            'other_last_read_id' => $this->getLastReadId((int)$room->id, $otherId),
            'chat_closed_at' => optional($room->closed_at)->toISOString(),
            'chat_closed_by' => $room->closed_by,
        ]);
    }

    public function typing(TypingRequest $request, ChatRoom $room)
    {
        $this->ensureParticipant($room);

        $me = (int)auth()->id();
        $data = $request->validated();

        $isTyping = (bool)($data['is_typing'] ?? false);
        $this->typingService->setTyping($room, $me, $isTyping);

        // Update typing inside the room.
        broadcast(new TypingUpdated($room->uuid, $me, $isTyping))->toOthers();

        // Also notify the other participant on their per-user channel so the chat list can show typing
        // without subscribing to every room channel (scales to many rooms).
        $otherId = $this->otherUserId($room, $me);
        broadcast(new UserTypingUpdated($otherId, $room->uuid, $me, $isTyping));

        return response()->json(['ok' => true]);
    }

public function seen(SeenRequest $request, ChatRoom $room)
    {
        // Explicit endpoint (optional) to mark messages seen
        $this->ensureParticipant($room);

        $me = (int)auth()->id();

        $otherLatestId = Message::where('chat_room_id', $room->id)
            ->where('user_id', '!=', $me)
            ->max('id');

        if ($otherLatestId) {
            if ($this->markReadUpTo($room, $me, (int)$otherLatestId)) {
            broadcast(new SeenUpdated($room->uuid, $me, (int)$otherLatestId, now()->toISOString()))->toOthers();
            $this->broadcastChatListForBoth($room);
        }

        }

        return response()->json(['ok' => true]);
    }

    public function toggleHeart(Request $request, ChatRoom $room, Message $message)
    {
        $this->ensureParticipant($room);

        if ((int)$message->chat_room_id !== (int)$room->id) {
            abort(404);
        }

        $this->authorize('react', $message);

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

        $this->authorize('delete', $message);

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
            'client_message_id' => $m->client_message_id,
            'body' => $m->body,
            'reply_to_id' => $m->reply_to_id,
            'reply_to' => $reply,
            'heart_count' => (int)($m->heart_count ?? 0),
            'my_hearted' => (bool)($m->my_hearted ?? false),
            'created_at' => optional($m->created_at)->toISOString(),
            // We no longer rely on DB per-message read_at writes at scale.
            // Client marks messages as "seen" using SeenUpdated + other_last_read_id.
            'read_at' => optional($m->read_at)->toISOString(),
            'deleted_at' => optional($m->deleted_at)->toISOString(),
        ];
    }

    private function updateReplyStats(ChatRoom $room, Message $msg): void
    {
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

        // Bust short-lived chat-list cache so the next fetch reflects this change immediately.
        $this->chatListCacheService->forgetForUsers($u1, $u2);

        broadcast(new ChatListUpdated($u1, ['room_uuid' => $room->uuid]))->toOthers();
        broadcast(new ChatListUpdated($u2, ['room_uuid' => $room->uuid]))->toOthers();
    }
}
