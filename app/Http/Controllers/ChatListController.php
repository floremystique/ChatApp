<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatListController extends Controller
{
    /**
     * Legacy endpoint kept for compatibility with older pages.
     * Optimized to avoid N+1 unread-count queries.
     */
    public function poll(Request $request)
    {
        $me = (int)auth()->id();

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
                'other.name as other_name',
                'lm.body as last_body',
                'lm.created_at as last_created_at',
                DB::raw('COALESCE(r.unread_count, 0) as unread'),
                DB::raw("$typingExpr as other_typing_until"),
            ])
            ->orderByDesc('chat_rooms.updated_at')
            ->limit(200)
            ->get();

        $data = $rows->map(function ($row) {
            $typing = false;
            if ($row->other_typing_until) {
                try {
                    $typing = now()->lt(\Illuminate\Support\Carbon::parse($row->other_typing_until));
                } catch (\Throwable $e) {
                    $typing = false;
                }
            }

            return [
                'room_uuid' => $row->uuid,
                'other_name' => $row->other_name ?? 'Unknown',
                'last_body' => $row->last_body ?? '',
                'last_at' => optional($row->last_created_at ? \Illuminate\Support\Carbon::parse($row->last_created_at) : null)->toISOString(),
                'unread' => (int)$row->unread,
                'typing' => (bool)$typing,
            ];
        });

        return response()->json($data);
    }
}
