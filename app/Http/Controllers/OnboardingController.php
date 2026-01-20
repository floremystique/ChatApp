<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\Profile;

use App\Models\MatchQuestion;
use App\Models\UserMatchAnswer;
use App\Models\UserMatchFeature;

use App\Models\MatchQuestionOption;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    
    public function create()
    {
        $user = auth()->user();

        // Always return a Profile instance (even if not saved yet)
        $profile = Profile::with('tags')->firstOrNew([
            'user_id' => $user->id,
        ]);

        // Ensure tags is a Collection even for new profiles
        $profile->setRelation('tags', $profile->tags ?? collect());

        $tags = Tag::orderBy('name')->get();

        return view('profile.onboarding', compact('profile', 'tags'));
    }


    // POST /onboarding
    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|string|max:50',
            'dob' => 'nullable|date',
            'bio' => 'nullable|string|max:120',
            'tags' => 'array',
            'tags.*' => 'integer',
        ]);

        $profile = Profile::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'looks_matter' => $request->has('looks_matter'),
                'mode' => $data['mode'],
                'dob' => $data['dob'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]
        );

        $profile->tags()->sync($data['tags'] ?? []);

        return redirect()->route('match');
    }

    public function quiz()
    {
        $questions = MatchQuestion::where('is_active', 1)
            ->with(['options' => fn($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();

        $existing = UserMatchAnswer::where('user_id', auth()->id())->get()->keyBy('match_question_id');

        return view('profile.quiz', compact('questions','existing'));
    }

    public function quizStore(Request $request)
    {
        $userId = auth()->id();

        // Save all single-choice answers:
        $answers = $request->input('answers', []); // [question_id => option_id]

        foreach ($answers as $qid => $oid) {
            UserMatchAnswer::updateOrCreate(
                ['user_id' => $userId, 'match_question_id' => (int)$qid],
                ['match_question_option_id' => (int)$oid, 'answer_payload' => null]
            );
        }

        $this->recomputeFeatures($userId);

        return redirect()->route('match');
    }

    private function recomputeFeatures(int $userId): void
    {
        // One query: answers joined with options + questions (no N+1)
        $rows = DB::table('user_match_answers as a')
            ->join('match_question_options as o', 'o.id', '=', 'a.match_question_option_id')
            ->join('match_questions as q', 'q.id', '=', 'a.match_question_id')
            ->where('a.user_id', $userId)
            ->whereNotNull('a.match_question_option_id')
            ->select([
                'q.key as qkey',
                'o.value as ovalue',
                'o.feature_deltas as deltas',
            ])
            ->get();

        // baseline
        $stability = 50;
        $values = 50;
        $trust = 50;
        $conflictRisk = 50;

        // direction fields
        $intent = null;
        $marriageIntent = null;
        $marriageTimeline = null;
        $kidsPref = null;
        $faithImportance = null;
        $faithMustMatch = false;

        foreach ($rows as $r) {
            $d = is_string($r->deltas) ? json_decode($r->deltas, true) : (array)$r->deltas;
            if (!is_array($d)) $d = [];

            $stability    += (int)($d['stability'] ?? 0);
            $values       += (int)($d['values'] ?? 0);
            $trust        += (int)($d['trust'] ?? 0);
            $conflictRisk += (int)($d['conflict_risk'] ?? 0);

            // Persist key decisions
            switch ($r->qkey) {
                case 'intent':
                    $intent = $r->ovalue;
                    break;
                case 'marriage_intent':
                    $marriageIntent = $r->ovalue;
                    break;
                case 'marriage_timeline':
                    $marriageTimeline = $r->ovalue;
                    break;
                case 'kids_pref':
                    $kidsPref = $r->ovalue;
                    break;
                case 'faith_importance':
                    $faithImportance = is_numeric($r->ovalue) ? (int)$r->ovalue : null;
                    break;
                case 'faith_must_match':
                    $faithMustMatch = ($r->ovalue === 'yes');
                    break;
            }
        }

        // Defaults for your “Serious” ecosystem
        if (!$intent) $intent = 'marriage';

        $clamp = fn($x) => max(0, min(100, (int)$x));

        UserMatchFeature::updateOrCreate(
            ['user_id' => $userId],
            [
                'stability_score' => $clamp($stability),
                'values_score'    => $clamp($values),
                'trust_score'     => $clamp($trust),
                'conflict_risk'   => $clamp($conflictRisk),

                'intent' => $intent,
                'marriage_intent' => $marriageIntent,
                'marriage_timeline' => $marriageTimeline,
                'kids_pref' => $kidsPref,
                'faith_importance' => $faithImportance,
                'faith_must_match' => (bool)$faithMustMatch,

                'computed_at' => now(),
            ]
        );
    }
}
