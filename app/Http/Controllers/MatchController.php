<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Profile;

class MatchController extends Controller
{
    public function index()
    {
        $me = auth()->user();

        // Load my profile + tags
        $myProfile = $me->profile()->with('tags:id,name')->first();
        if (!$myProfile) {
            return redirect()->route('onboarding');
        }

        $myTagIds = $myProfile->tags->pluck('id')->all();

        // Load scoring rules from DB (no hardcoding)
        $rules = \Illuminate\Support\Facades\DB::table('match_scoring_rules')
            ->where('is_active', 1)
            ->pluck('value', 'rule_key');

        $pointsPerSharedTag   = (int)($rules['points_per_shared_tag'] ?? 0);
        $pointsSameMode       = (int)($rules['points_same_mode'] ?? 0);
        $pointsLooksAlignment = (int)($rules['points_looks_alignment'] ?? 0);

        // Load other users' profiles + tags + user
        $profiles = \App\Models\Profile::query()
            ->where('user_id', '!=', $me->id)
            ->with(['tags:id,name', 'user:id,name'])
            ->get();

        $users = $profiles->map(function ($p) use (
            $myProfile,
            $myTagIds,
            $pointsPerSharedTag,
            $pointsSameMode,
            $pointsLooksAlignment
        ) {
            // Shared tags (intersection)
            $commonTags = $p->tags->whereIn('id', $myTagIds)->values();
            $commonCount = $commonTags->count();

            // Score components
            $tagScore = $commonCount * $pointsPerSharedTag;
            $modeMatch = ($p->mode === $myProfile->mode);
            $modeScore = $modeMatch ? $pointsSameMode : 0;

            $looksAligned = ((bool)$p->looks_matter === (bool)$myProfile->looks_matter);
            $looksScore = $looksAligned ? $pointsLooksAlignment : 0;

            $score = $tagScore + $modeScore + $looksScore;

            // Attach computed fields for the Blade
            $p->score = $score;
            $p->common_tags = $commonTags->pluck('name');
            $p->score_breakdown = [
                'shared_tags' => $commonCount,
                'tag_score' => $tagScore,
                'mode_match' => $modeMatch,
                'mode_score' => $modeScore,
                'looks_aligned' => $looksAligned,
                'looks_score' => $looksScore,
            ];

            return $p;
        })
        ->sortByDesc('score')
        ->values();

        return view('match.index', compact('users'));
    }


    public function start($otherUserId)
    {
        $me = auth()->id();
        $other = (int) $otherUserId;
        abort_if($me === $other, 400);

        $low = min($me, $other);
        $high = max($me, $other);

        $room = ChatRoom::firstOrCreate(
            ['user_low' => $low, 'user_high' => $high],
            [
                'uuid' => (string) Str::uuid(),
                'user_one' => $low,
                'user_two' => $high,
            ]
        );

        // if old rows exist without uuid, fix it
        if (!$room->uuid) {
            $room->uuid = (string) Str::uuid();
            $room->save();
        }

        return redirect()->route('chat.show', $room); //  will use uuid in URL
    }

}
