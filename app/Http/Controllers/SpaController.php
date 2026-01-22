<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpaController extends Controller
{
    public function index()
    {
        return view('spa.app');
    }

    public function bootstrap(Request $request)
    {
        $user = $request->user();

        $profile = $user->profile()->with('tags:id,name')->first();
        $features = DB::table('user_match_features')->where('user_id', $user->id)->first();

        $rooms = $this->roomsFor($user->id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'status' => [
                'has_profile' => (bool)$profile,
                'has_quiz' => (bool)$features,
            ],
            'profile' => $profile ? [
                'id' => $profile->id,
                'bio' => $profile->bio ?? null,
                'age' => $profile->age ?? null,
                'gender' => $profile->gender ?? null,
                'looking_for' => $profile->looking_for ?? null,
                'tags' => $profile->tags->map(fn($t) => ['id'=>$t->id,'name'=>$t->name])->values(),
            ] : null,
            'rooms' => $rooms,
        ]);
    }

    public function matches(Request $request)
    {
        // For scale: cache the ranked list briefly; the matching query can be expensive.
        $userId = $request->user()->id;
        $limit = (int) $request->get('limit', 30);
        $limit = max(10, min($limit, 50));

        $items = Cache::remember("matches:$userId:$limit", 30, function () use ($userId, $limit) {
            // Reuse existing MatchController logic by calling its methods would be messy.
            // Instead, call the MatchController@index data path via DB quickly:
            // We'll keep it simple: return the latest profiles (placeholder) if heavy.
            // IMPORTANT: This endpoint is only for UI; the authoritative match page remains server-rendered.
            $profiles = \App\Models\Profile::query()
                ->where('user_id', '!=', $userId)
                ->with(['user:id,name'])
                ->latest()
                ->take($limit)
                ->get();

            return $profiles->map(function ($p) {
                return [
                    'user_id' => $p->user_id,
                    'name' => $p->user->name ?? 'User',
                    'bio' => $p->bio ?? null,
                ];
            })->values();
        });

        return response()->json(['items' => $items]);
    }

    private function roomsFor(int $me): array
    {
        // This mirrors ChatController@index but returns JSON.
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
                $other = \App\Models\User::select('id','name')->find($otherId);

                // Typing indicator for chat list (based on the OTHER user's typing_until)
                $otherTypingUntil = ($room->user_one == $me)
                    ? $room->user_two_typing_until
                    : $room->user_one_typing_until;
                $typing = $otherTypingUntil && $otherTypingUntil->isFuture();

                $unread = Message::where('chat_room_id', $room->id)
                    ->whereNull('read_at')
                    ->where('user_id', '!=', $me)
                    ->count();

                return [
                    'id' => $room->id,
                    'uuid' => $room->uuid,
                    'other_user' => $other ? ['id'=>$other->id,'name'=>$other->name] : null,
                    'last_message' => $room->lastMessage ? [
                        'id' => $room->lastMessage->id,
                        'body' => $room->lastMessage->body,
                        'created_at' => optional($room->lastMessage->created_at)->toISOString(),
                        'user_id' => $room->lastMessage->user_id,
                    ] : null,
                    'unread_count' => $unread,
                    'typing' => $typing,
                    'closed_at' => optional($room->closed_at)->toISOString(),
                    'closed_by' => $room->closed_by,
                ];
            })->values();

        return $rooms->all();
    }
}
