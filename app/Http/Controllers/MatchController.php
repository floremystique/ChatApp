<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\ChatRoom;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

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

        // Require serious quiz features
        $myF = DB::table('user_match_features')
            ->where('user_id', $me->id)
            ->first();

        if (!$myF) {
            return redirect()->route('onboarding.quiz');
        }

        $myTagIds = $myProfile->tags->pluck('id')->all();

        // Keep your existing DB-based scoring rules (tags/mode/looks)
        $rules = DB::table('match_scoring_rules')
            ->where('is_active', 1)
            ->pluck('value', 'rule_key');

        $pointsPerSharedTag   = (int)($rules['points_per_shared_tag'] ?? 0);
        $pointsSameMode       = (int)($rules['points_same_mode'] ?? 0);
        $pointsLooksAlignment = (int)($rules['points_looks_alignment'] ?? 0);

        // ----------------------------
        // Candidate pool query (FAST)
        // ----------------------------
        $q = DB::table('profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('user_match_features as f', 'f.user_id', '=', 'u.id')
            ->leftJoin('user_chat_stats as s', 's.user_id', '=', 'u.id')
            ->where('u.id', '!=', $me->id);

        // Optional: keep your "mode" alignment as a soft filter (do not hard block)
        if (!empty($myProfile->mode)) {
            $q->where(function ($w) use ($myProfile) {
                $w->whereNull('p.mode')->orWhere('p.mode', $myProfile->mode);
            });
        }

        // ----------------------------
        // Trust-first marriage filters
        // ----------------------------
        if (($myF->marriage_intent ?? null) === 'yes') {
            $q->whereIn('f.marriage_intent', ['yes', 'unsure']);
        }

        if (in_array(($myF->kids_pref ?? null), ['want', 'dont'], true)) {
            $q->whereIn('f.kids_pref', [$myF->kids_pref, 'open', 'have']);
        }

        if ((int)($myF->faith_must_match ?? 0) === 1 && !is_null($myF->faith_importance ?? null)) {
            $q->whereNotNull('f.faith_importance')
              ->where('f.faith_importance', '>=', 40);
        }

        // ----------------------------
        // Shared tags subquery (ONLY ONCE) + reusable SQL string
        // ----------------------------
        $sharedTagsSql = '0';

        if (!empty($myTagIds)) {
            $tagIdList = implode(',', array_map('intval', $myTagIds));

            $sharedTagsSql = "(
                SELECT COUNT(*)
                FROM profile_tag pt
                WHERE pt.profile_id = p.id
                AND pt.tag_id IN ($tagIdList)
            )";
        }

        // Add shared_tags explicitly aliased
        $q->addSelect(DB::raw("{$sharedTagsSql} AS shared_tags"));

        // ----------------------------
        // Reply speed -> responsiveness score (0..100)
        // ----------------------------
        $myStability = (int)($myF->stability_score ?? 50);
        $myValues    = (int)($myF->values_score ?? 50);
        $myTrust     = (int)($myF->trust_score ?? 50);

        $responsivenessSql = "
            CASE
                WHEN s.reply_time_ema_sec IS NULL OR s.reply_time_ema_sec = 0 THEN 50
                WHEN s.reply_time_ema_sec <= 900 THEN 100
                WHEN s.reply_time_ema_sec <= 21600 THEN 40 + ( (21600 - s.reply_time_ema_sec) * 60 / (21600 - 900) )
                WHEN s.reply_time_ema_sec <= 86400 THEN 10 + ( (86400 - s.reply_time_ema_sec) * 30 / (86400 - 21600) )
                ELSE 10
            END
        ";

        // ----------------------------
        // Final score (trust-first marriage mode)
        // IMPORTANT: Cast UNSIGNED numeric columns to SIGNED to avoid MySQL 1690 overflow.
        // ----------------------------
        $finalScoreSql = "
        (
          (
            (0.55 * (
                0.70 * GREATEST(0, 100 - ABS(CAST(f.stability_score AS SIGNED) - CAST({$myStability} AS SIGNED))) +
                0.30 * GREATEST(0, 100 - CAST(f.conflict_risk AS SIGNED))
            )) +
            (0.30 * GREATEST(0, 100 - ABS(CAST(f.values_score AS SIGNED) - CAST({$myValues} AS SIGNED)))) +
            (0.15 * (
                0.65 * GREATEST(0, 100 - ABS(CAST(f.trust_score AS SIGNED) - CAST({$myTrust} AS SIGNED))) +
                0.35 * ({$responsivenessSql})
            ))
          )
          + (0.10 * LEAST(100, (({$sharedTagsSql}) * 15)))
        )
        ";

        // ----------------------------
        // Base fields + computed fields (aliased)
        // ----------------------------
        $q->addSelect([
            'p.id as profile_id',
            'u.id as user_id',
            'f.stability_score',
            'f.values_score',
            'f.trust_score',
            'f.conflict_risk',
            'f.marriage_intent',
            'f.marriage_timeline',
            'f.kids_pref',
            'f.faith_importance',
            'f.faith_must_match',
        ]);

        $q->addSelect(DB::raw("{$responsivenessSql} AS responsiveness_score"));
        $q->addSelect(DB::raw("{$finalScoreSql} AS final_score"));

        // Minimum floor for serious quality
        $q->where('f.stability_score', '>=', 35);

        $rows = $q->orderByDesc('final_score')->limit(80)->get();

        if ($rows->isEmpty()) {
            return view('match.index', ['users' => collect()]);
        }

        // Hydrate real Profile models so your existing Blade keeps working
        $profileIds = $rows->pluck('profile_id')->all();

        $profiles = Profile::query()
            ->whereIn('id', $profileIds)
            ->with(['tags:id,name', 'user:id,name'])
            ->get()
            ->keyBy('id');

        // Map in ranked order
        $users = $rows->map(function ($r) use (
            $profiles,
            $myProfile,
            $myTagIds,
            $pointsPerSharedTag,
            $pointsSameMode,
            $pointsLooksAlignment,
            $myF
        ) {
            $p = $profiles->get($r->profile_id);
            if (!$p) return null;

            $commonTags  = $p->tags->whereIn('id', $myTagIds)->values();
            $commonCount = $commonTags->count();

            $tagScore   = $commonCount * $pointsPerSharedTag;
            $modeMatch  = ($p->mode === $myProfile->mode);
            $modeScore  = $modeMatch ? $pointsSameMode : 0;

            $looksAligned = ((bool)$p->looks_matter === (bool)$myProfile->looks_matter);
            $looksScore   = $looksAligned ? $pointsLooksAlignment : 0;

            $p->score = (int)round((float)($r->final_score ?? 0));

            $p->common_tags = $commonTags->pluck('name');

            $p->score_breakdown = [
                'shared_tags'    => $commonCount,
                'tag_score'      => $tagScore,
                'mode_match'     => $modeMatch,
                'mode_score'     => $modeScore,
                'looks_aligned'  => $looksAligned,
                'looks_score'    => $looksScore,

                'stability'      => (int)($r->stability_score ?? 50),
                'values'         => (int)($r->values_score ?? 50),
                'trust'          => (int)($r->trust_score ?? 50),
                'conflict_risk'  => (int)($r->conflict_risk ?? 50),
                'responsiveness' => (int)round((float)($r->responsiveness_score ?? 50)),
                'marriage_intent'   => $r->marriage_intent ?? null,
                'marriage_timeline' => $r->marriage_timeline ?? null,
                'kids_pref'         => $r->kids_pref ?? null,
            ];

            $insights = [];

            if (($myF->marriage_intent ?? null) === 'yes' && in_array(($r->marriage_intent ?? ''), ['yes', 'unsure'], true)) {
                $insights[] = 'Marriage-minded alignment';
            }

            if (!empty($myF->kids_pref) && !empty($r->kids_pref) && $myF->kids_pref === $r->kids_pref) {
                $insights[] = 'Kids preference matches';
            }

            $conflictSafety = 100 - (int)($r->conflict_risk ?? 50);
            if ($conflictSafety >= 70) $insights[] = 'Low conflict-risk style';

            if (((int)($r->responsiveness_score ?? 50)) >= 75) $insights[] = 'Typically replies fast';

            if (($r->shared_tags ?? 0) >= 2) $insights[] = 'Shared interests';

            $p->insights = $insights;

            return $p;
        })->filter()->values();

        return view('match.index', compact('users'));
    }

    public function start($otherUserId)
    {
        $me = auth()->id();
        $other = (int)$otherUserId;
        abort_if($me === $other, 400);

        $low = min($me, $other);
        $high = max($me, $other);

        $room = ChatRoom::firstOrCreate(
            ['user_low' => $low, 'user_high' => $high],
            [
                'uuid' => (string)Str::uuid(),
                'user_one' => $low,
                'user_two' => $high,
            ]
        );

        if (!$room->uuid) {
            $room->uuid = (string)Str::uuid();
            $room->save();
        }

        return redirect()->route('chat.show', $room);
    }
}
