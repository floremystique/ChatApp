<?php

namespace App\Http\Controllers;

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

        $rooms = $this->roomsFor((int)$user->id);

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
                'tags' => $profile->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            ] : null,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Lightweight endpoint for frequent chat-list refreshes.
     */
    public function rooms(Request $request)
    {
        $userId = (int)$request->user()->id;
        return response()->json(['rooms' => $this->roomsFor($userId)]);
    }

    public function matches(Request $request)
    {
        $userId = $request->user()->id;
        $limit = (int)$request->get('limit', 30);
        $limit = max(10, min($limit, 50));

        $items = Cache::remember("matches:$userId:$limit", 30, function () use ($userId, $limit) {
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
        // Cache briefly to collapse bursts of "chatlist.updated" events.
        return Cache::remember("rooms:$me", 2, function () use ($me) {
            $otherJoin = "CASE WHEN chat_rooms.user_one = $me THEN chat_rooms.user_two ELSE chat_rooms.user_one END";
            $typingExpr = "CASE WHEN chat_rooms.user_one = $me THEN chat_rooms.user_two_typing_until ELSE chat_rooms.user_one_typing_until END";

            $rows = DB::table('chat_rooms')
                ->where(function ($q) use ($me) {
                    $q->where('chat_rooms.user_one', $me)
                      ->orWhere('chat_rooms.user_two', $me);
                })
                ->leftJoin('chat_room_reads as r', function ($j) use ($me) {
                    $j->on('r.chat_room_id', '=', 'chat_rooms.id')
                      ->where('r.user_id', '=', $me);
                })
                ->leftJoin('users as other', 'other.id', '=', DB::raw($otherJoin))
                ->leftJoin('messages as lm', 'lm.id', '=', 'chat_rooms.last_message_id')
                ->select([
                    'chat_rooms.id',
                    'chat_rooms.uuid',
                    'chat_rooms.closed_at',
                    'chat_rooms.closed_by',
                    'chat_rooms.updated_at',
                    'other.id as other_id',
                    'other.name as other_name',
                    'lm.id as last_id',
                    'lm.body as last_body',
                    'lm.created_at as last_created_at',
                    DB::raw('COALESCE(r.unread_count, 0) as unread_count'),
                    DB::raw("$typingExpr as other_typing_until"),
                ])
                ->orderByDesc('chat_rooms.updated_at')
                ->limit(200)
                ->get();

            return $rows->map(function ($row) {
                $typing = false;
                if ($row->other_typing_until) {
                    try {
                        $typing = now()->lt(\Illuminate\Support\Carbon::parse($row->other_typing_until));
                    } catch (\Throwable $e) {
                        $typing = false;
                    }
                }

                return [
                    'id' => (int)$row->id,
                    'uuid' => $row->uuid,
                    'other_user' => $row->other_id ? ['id' => (int)$row->other_id, 'name' => $row->other_name] : null,
                    'last_message' => $row->last_id ? [
                        'id' => (int)$row->last_id,
                        'body' => $row->last_body,
                        'created_at' => optional($row->last_created_at ? \Illuminate\Support\Carbon::parse($row->last_created_at) : null)->toISOString(),
                    ] : null,
                    'unread_count' => (int)$row->unread_count,
                    'typing' => (bool)$typing,
                    'closed_at' => optional($row->closed_at ? \Illuminate\Support\Carbon::parse($row->closed_at) : null)->toISOString(),
                    'closed_by' => $row->closed_by,
                ];
            })->values()->all();
        });
    }
}
