<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Profile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    public function index()
    {
        $me = auth()->user();

        // ----------------------------
        // 1) REQUIRE: my profile + tags
        // ----------------------------
        $myProfile = $me->profile()->with('tags:id,name')->first();
        if (!$myProfile) {
            return redirect()->route('onboarding');
        }

        // ----------------------------
        // 2) REQUIRE: my serious quiz features
        // ----------------------------
        $myF = DB::table('user_match_features')->where('user_id', $me->id)->first();
        if (!$myF) {
            return redirect()->route('onboarding.quiz');
        }

        // ----------------------------
        // 3) Load basic scoring rules (existing table)
        // ----------------------------
        $rules = DB::table('match_scoring_rules')
            ->where('is_active', 1)
            ->pluck('value', 'rule_key');

        $pointsPerSharedTag   = (int)($rules['points_per_shared_tag'] ?? 2);
        $pointsSameMode       = (int)($rules['points_same_mode'] ?? 2);
        $pointsLooksAlignment = (int)($rules['points_looks_alignment'] ?? 1);

        // ----------------------------
        // 4) Normalize my fields
        // ----------------------------
        $myMode  = strtolower(trim((string)($myProfile->mode ?? '')));
        $myLooks = (bool)($myProfile->looks_matter ?? false);

        $myTagIds   = $myProfile->tags->pluck('id')->all();
        $myTagNames = $myProfile->tags->pluck('name')->map(fn ($t) => strtolower(trim($t)))->all();

        $myStability = (int)($myF->stability_score ?? 50);
        $myValues    = (int)($myF->values_score ?? 50);
        $myTrust     = (int)($myF->trust_score ?? 50);
        $myConflict  = (int)($myF->conflict_risk ?? 50);

        $myIntent          = (string)($myF->intent ?? '');
        $myMarriageIntent  = (string)($myF->marriage_intent ?? '');
        $myMarriageTime    = (string)($myF->marriage_timeline ?? '');
        $myKids            = (string)($myF->kids_pref ?? '');
        $myFaithImportance = is_null($myF->faith_importance ?? null) ? null : (int)$myF->faith_importance;
        $myFaithMustMatch  = (int)($myF->faith_must_match ?? 0);

        // ----------------------------
        // 5) Candidate pool query (NO preference hard-filters)
        // ----------------------------
        $q = DB::table('profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('user_match_features as f', 'f.user_id', '=', 'u.id')
            ->leftJoin('user_chat_stats as s', 's.user_id', '=', 'u.id')
            ->where('u.id', '!=', $me->id);

        // basic safety floor only
        $q->where('f.stability_score', '>=', 20);

        // Shared tags count
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
        $q->addSelect(DB::raw("{$sharedTagsSql} AS shared_tags"));

        // Responsiveness score (0..100)
        $responsivenessSql = "
            CASE
                WHEN s.reply_time_ema_sec IS NULL OR s.reply_time_ema_sec = 0 THEN 50
                WHEN s.reply_time_ema_sec <= 900 THEN 100
                WHEN s.reply_time_ema_sec <= 21600 THEN 40 + ( (21600 - s.reply_time_ema_sec) * 60 / (21600 - 900) )
                WHEN s.reply_time_ema_sec <= 86400 THEN 10 + ( (86400 - s.reply_time_ema_sec) * 30 / (86400 - 21600) )
                ELSE 10
            END
        ";
        $q->addSelect(DB::raw("{$responsivenessSql} AS responsiveness_score"));

        // Fields we need
        $q->addSelect([
            'p.id as profile_id',
            'p.mode as profile_mode',
            'p.looks_matter as profile_looks_matter',
            'u.id as user_id',
            'u.name as user_name',
            'f.stability_score',
            'f.values_score',
            'f.trust_score',
            'f.conflict_risk',
            'f.intent',
            'f.marriage_intent',
            'f.marriage_timeline',
            'f.kids_pref',
            'f.faith_importance',
            'f.faith_must_match',
        ]);

        $rows = $q->limit(200)->get();

        if ($rows->isEmpty()) {
            return view('match.index', ['users' => collect()]);
        }

        // Hydrate Profiles for Blade
        $profileIds = $rows->pluck('profile_id')->all();
        $profiles = Profile::query()
            ->whereIn('id', $profileIds)
            ->with(['tags:id,name', 'user:id,name'])
            ->get()
            ->keyBy('id');

        // ----------------------------
        // 6) Matrices
        // ----------------------------
        $modePenaltyMatrix = [
            'dark' => [
                'casual' => 35, 'friendly' => 25, 'deep' => 5, 'supportive' => 10, 'matchmaking' => 15,
            ],
            'deep' => [
                'casual' => 20, 'friendly' => 10, 'dark' => 5, 'supportive' => 8, 'matchmaking' => 10,
            ],
            'supportive' => [
                'casual' => 12, 'friendly' => 8, 'dark' => 10, 'deep' => 8, 'matchmaking' => 10,
            ],
            'friendly' => [
                'dark' => 20, 'deep' => 10, 'matchmaking' => 10, 'supportive' => 6, 'casual' => 5,
            ],
            'casual' => [
                'dark' => 40, 'deep' => 20, 'matchmaking' => 15, 'supportive' => 10, 'friendly' => 5,
            ],
            'matchmaking' => [
                'casual' => 12, 'friendly' => 10, 'dark' => 15, 'deep' => 8, 'supportive' => 10,
            ],
        ];

        $getModePenalty = function (?string $my, ?string $their) use ($modePenaltyMatrix) {
            $my = strtolower(trim((string)$my));
            $their = strtolower(trim((string)$their));
            if ($my === '' || $their === '' || $my === $their) return 0;
            return (int)($modePenaltyMatrix[$my][$their] ?? $modePenaltyMatrix[$their][$my] ?? 0);
        };

        $tagAffinity = [
            'anime' => ['funny' => 4, 'artistic' => 4, 'gaming' => 3, 'casual' => 2, 'deep' => 1],
            'gaming' => ['funny' => 3, 'anime' => 3, 'supportive' => 2, 'deep' => 1],
            'matchmaking' => ['supportive' => 4, 'deep' => 3, 'inspiring' => 2, 'business' => 1],
            'supportive' => ['deep' => 3, 'matchmaking' => 4, 'inspiring' => 2, 'friendly' => 2],
            'deep' => ['supportive' => 3, 'matchmaking' => 3, 'inspiring' => 2],
            'artistic' => ['anime' => 3, 'inspiring' => 3, 'friendly' => 2],
            'business' => ['inspiring' => 2, 'deep' => 1],
            'funny' => ['friendly' => 2, 'anime' => 2, 'gaming' => 2],
        ];

        $computeTagAffinityBonus = function (array $myNames, array $theirNames) use ($tagAffinity) {
            $mySet = array_unique(array_map(fn($x) => strtolower(trim($x)), $myNames));
            $theirSet = array_unique(array_map(fn($x) => strtolower(trim($x)), $theirNames));
            $bonus = 0;
            foreach ($mySet as $m) {
                if (!isset($tagAffinity[$m])) continue;
                foreach ($theirSet as $t) {
                    if ($m === $t) continue;
                    $bonus += (int)($tagAffinity[$m][$t] ?? 0);
                }
            }
            return min(18, $bonus);
        };

        $intentPenalty = [
            'marriage' => ['serious' => 6, 'dating' => 18, 'friends' => 28],
            'serious'  => ['marriage' => 6, 'dating' => 12, 'friends' => 22],
            'dating'   => ['marriage' => 18, 'serious' => 12, 'friends' => 10],
            'friends'  => ['marriage' => 28, 'serious' => 22, 'dating' => 10],
        ];

        $marriagePenalty = [
            'yes' => ['unsure' => 8, 'no' => 25],
            'unsure' => ['yes' => 8, 'no' => 14],
            'no' => ['yes' => 25, 'unsure' => 14],
        ];

        $kidsPenalty = [
            'want' => ['dont' => 35, 'open' => 10, 'have' => 12],
            'dont' => ['want' => 35, 'open' => 10, 'have' => 8],
            'open' => ['want' => 10, 'dont' => 10, 'have' => 6],
            'have' => ['want' => 12, 'dont' => 8, 'open' => 6],
        ];

        $timelinePenalty = [
            '1-2y' => ['3-5y' => 8, 'later' => 12, 'depends' => 5],
            '3-5y' => ['1-2y' => 8, 'later' => 8, 'depends' => 5],
            'later' => ['1-2y' => 12, '3-5y' => 8, 'depends' => 6],
            'depends' => ['1-2y' => 5, '3-5y' => 5, 'later' => 6],
        ];

        $getPenalty = function (string $mine, string $theirs, array $matrix) {
            $mine = strtolower(trim($mine));
            $theirs = strtolower(trim($theirs));
            if ($mine === '' || $theirs === '' || $mine === $theirs) return 0;
            return (int)($matrix[$mine][$theirs] ?? $matrix[$theirs][$mine] ?? 0);
        };

        // ----------------------------
        // 7) Helpers: band + bond chips (connection-focused, not judging)
        // ----------------------------
        $scoreBand = function (int $score): array {
            // fallback ranges (always correct even if DB table is wrong)
            if ($score >= 85) return ['label' => 'Exceptional match', 'description' => 'Strong alignment'];
            if ($score >= 70) return ['label' => 'Strong match', 'description' => 'Good foundation'];
            if ($score >= 55) return ['label' => 'Promising match', 'description' => 'Some strong overlaps'];
            if ($score >= 40) return ['label' => 'Mixed match', 'description' => 'Could work'];
            if ($score >= 20) return ['label' => 'Light match', 'description' => 'Worth a try'];
            return ['label' => 'Low match', 'description' => 'Weak connection'];
        };

        $bondWord = function (int $x, array $thresholds): string {
            // thresholds: [highWord, midWord, lowWord, veryLowWord]
            // x is 0..100
            if ($x >= 80) return $thresholds[0];
            if ($x >= 60) return $thresholds[1];
            if ($x >= 40) return $thresholds[2];
            return $thresholds[3];
        };

        // ----------------------------
        // 8) Compute final score in PHP + attach band + bond chips
        // ----------------------------
        $ranked = $rows->map(function ($r) use (
            $profiles,
            $myTagIds,
            $myTagNames,
            $pointsPerSharedTag,
            $pointsSameMode,
            $pointsLooksAlignment,
            $myMode,
            $myLooks,
            $myStability,
            $myValues,
            $myTrust,
            $myConflict,
            $myIntent,
            $myMarriageIntent,
            $myMarriageTime,
            $myKids,
            $myFaithImportance,
            $myFaithMustMatch,
            $getModePenalty,
            $computeTagAffinityBonus,
            $getPenalty,
            $intentPenalty,
            $marriagePenalty,
            $kidsPenalty,
            $timelinePenalty,
            $scoreBand,
            $bondWord
        ) {
            $p = $profiles->get($r->profile_id);
            if (!$p) return null;

            $theirMode  = strtolower(trim((string)($p->mode ?? '')));
            $theirLooks = (bool)($p->looks_matter ?? false);

            $theirF = (object)[
                'stability' => (int)($r->stability_score ?? 50),
                'values' => (int)($r->values_score ?? 50),
                'trust' => (int)($r->trust_score ?? 50),
                'conflict' => (int)($r->conflict_risk ?? 50),
                'intent' => (string)($r->intent ?? ''),
                'marriage_intent' => (string)($r->marriage_intent ?? ''),
                'marriage_timeline' => (string)($r->marriage_timeline ?? ''),
                'kids_pref' => (string)($r->kids_pref ?? ''),
                'faith_importance' => is_null($r->faith_importance ?? null) ? null : (int)$r->faith_importance,
                'faith_must_match' => (int)($r->faith_must_match ?? 0),
                'responsiveness' => (int)round((float)($r->responsiveness_score ?? 50)),
            ];

            // Shared tags
            $commonTags  = $p->tags->whereIn('id', $myTagIds)->values();
            $commonCount = $commonTags->count();
            $tagScore    = $commonCount * $pointsPerSharedTag;

            // Tag affinity bonus
            $theirTagNames = $p->tags->pluck('name')->map(fn ($t) => strtolower(trim($t)))->all();
            $tagAffinityBonus = $computeTagAffinityBonus($myTagNames, $theirTagNames);

            // Mode + looks
            $modeMatch   = ($myMode !== '' && $theirMode !== '' && $myMode === $theirMode);
            $modeScore   = $modeMatch ? $pointsSameMode : 0;
            $modePenalty = $getModePenalty($myMode, $theirMode);

            $looksAligned = ($myLooks === $theirLooks);
            $looksScore   = $looksAligned ? $pointsLooksAlignment : 0;

            // Similarities (0..100)
            $simStability = max(0, 100 - abs($theirF->stability - $myStability));
            $simValues    = max(0, 100 - abs($theirF->values - $myValues));
            $simTrust     = max(0, 100 - abs($theirF->trust - $myTrust));

            $myConflictSafety    = max(0, 100 - (int)$myConflict);
            $theirConflictSafety = max(0, 100 - (int)$theirF->conflict);
            $simConflictSafety   = max(0, 100 - abs($theirConflictSafety - $myConflictSafety));

            // Core (kept realistic so penalties can pull score down)
            $core =
                (0.30 * $simStability) +
                (0.30 * $simValues) +
                (0.25 * $simTrust) +
                (0.15 * $simConflictSafety);

            // Small responsiveness influence
            $core += (0.08 * $theirF->responsiveness); // +0..8

            // Penalties
            $penalty = 0;
            $penalty += $getPenalty($myIntent, $theirF->intent, $intentPenalty);
            $penalty += $getPenalty($myMarriageIntent, $theirF->marriage_intent, $marriagePenalty);

            if (in_array(strtolower($myMarriageIntent), ['yes', 'unsure'], true) &&
                in_array(strtolower($theirF->marriage_intent), ['yes', 'unsure'], true)) {
                $penalty += $getPenalty($myMarriageTime, $theirF->marriage_timeline, $timelinePenalty);
            }

            $penalty += $getPenalty($myKids, $theirF->kids_pref, $kidsPenalty);

            if ($myFaithMustMatch === 1) {
                if (is_null($theirF->faith_importance)) {
                    $penalty += 12;
                } else {
                    if ($theirF->faith_importance < 40) $penalty += 18;
                    elseif ($theirF->faith_importance < 60) $penalty += 8;
                }
            }

            if ($myFaithMustMatch === 1 && (int)$theirF->faith_must_match === 1) {
                if (!is_null($myFaithImportance) && !is_null($theirF->faith_importance)) {
                    $gap = abs($myFaithImportance - $theirF->faith_importance);
                    if ($gap >= 50) $penalty += 10;
                    elseif ($gap >= 25) $penalty += 5;
                }
            }

            $penalty += $modePenalty;

            // Bonuses
            $bonus = 0;
            $bonus += min(18, $tagScore * 2);
            $bonus += $tagAffinityBonus;
            $bonus += ($modeScore * 4);
            $bonus += ($looksScore * 2);

            $final = (int)round(max(0, min(100, ($core + $bonus) - $penalty)));

            // Insights
            $insights = [];
            if ($modeMatch) $insights[] = 'Same conversation style';
            if ($modePenalty >= 30) $insights[] = 'Very different conversation style';
            elseif ($modePenalty >= 15) $insights[] = 'Different conversation style';

            if ($commonCount >= 2) $insights[] = 'Shared interests';
            elseif ($commonCount === 1) $insights[] = 'One shared interest';

            if ($theirConflictSafety >= 70) $insights[] = 'Similar conflict style';

            if ($theirF->responsiveness >= 75) $insights[] = 'Typically replies fast';
            elseif ($theirF->responsiveness <= 35) $insights[] = 'Slow reply rhythm';

            if ($getPenalty($myKids, $theirF->kids_pref, $kidsPenalty) >= 30) $insights[] = 'Kids preference mismatch';
            if ($getPenalty($myMarriageIntent, $theirF->marriage_intent, $marriagePenalty) >= 20) $insights[] = 'Marriage goal mismatch';

            // Attach to Profile (Blade-friendly)
            $p->score = $final;
            $p->common_tags = $commonTags->pluck('name');
            $p->insights = $insights;

            // Band (fixed regardless of DB band table issues)
            $p->band = $scoreBand($final);

            // Bond chips (1 word, connection-focused)
            $p->bond_chips = [
                'Stability' => $bondWord((int)round($simStability), ['Anchored', 'Steady', 'Testing Relationship', 'Fragile']),
                'Trust'     => $bondWord((int)round($simTrust),     ['Reserved',   'Open',   'Cautious', 'Guarded']),
                'Replies'   => $bondWord((int)$theirF->responsiveness, ['Flowing Replies', 'Steady Replies', 'Slow Replies', 'Stalled Replies']),
                'Conflict'  => $bondWord((int)round(($theirConflictSafety + $simConflictSafety) / 2), ['Calm', 'Adjustable', 'Tensions', 'Easygoing']),
            ];

            // keep details for debugging
            $p->score_breakdown = [
                'shared_tags'    => $commonCount,
                'tag_score'      => $tagScore,
                'mode_match'     => $modeMatch,
                'mode_score'     => $modeScore,
                'looks_aligned'  => $looksAligned,
                'looks_score'    => $looksScore,

                'mode_penalty'   => $modePenalty,
                'tag_affinity_bonus' => $tagAffinityBonus,

                // raw inputs
                'stability'      => (int)($r->stability_score ?? 50),
                'values'         => (int)($r->values_score ?? 50),
                'trust'          => (int)($r->trust_score ?? 50),
                'conflict_risk'  => (int)($r->conflict_risk ?? 50),
                'responsiveness' => (int)$theirF->responsiveness,

                // similarities (more meaningful)
                'sim_stability' => (int)round($simStability),
                'sim_values'    => (int)round($simValues),
                'sim_trust'     => (int)round($simTrust),
                'sim_conflict_safety' => (int)round($simConflictSafety),

                'core' => (float)$core,
                'bonus' => (int)$bonus,
                'penalty' => (int)$penalty,
            ];

            return $p;
        })->filter()->values();

        $users = $ranked->sortByDesc(fn ($p) => $p->score)->values();

        if (request()->routeIs('partials.matches') || request()->ajax()) {
            return view('match.partial', compact('users'));
        }

        return view('match.index', compact('users'));
    }

    public function start(\Illuminate\Http\Request $request, $otherUserId)
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

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'room' => [
                    'id' => $room->id,
                    'uuid' => $room->uuid,
                ],
            ]);
        }

        return redirect()->route('chat.show', $room);
    }
}
