<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MatchQuestion;
use App\Models\MatchQuestionOption;

class MatchQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing (safe for dev; remove if you want to preserve edits)
        MatchQuestionOption::query()->delete();
        MatchQuestion::query()->delete();

        $order = 1;

        $makeQ = function (string $key, string $category, string $title, ?string $subtitle, array $options) use (&$order) {
            $q = MatchQuestion::create([
                'key' => $key,
                'category' => $category,
                'ui_type' => 'single',
                'title' => $title,
                'subtitle' => $subtitle,
                'is_active' => true,
                'order' => $order++,
            ]);

            $optOrder = 1;
            foreach ($options as $opt) {
                MatchQuestionOption::create([
                    'match_question_id' => $q->id,
                    'label' => $opt['label'],
                    'value' => $opt['value'] ?? null,
                    'order' => $optOrder++,
                    'feature_deltas' => $opt['deltas'] ?? [],
                ]);
            }

            return $q;
        };

        /*
        Scoring philosophy (B: modern, international, marriage-minded):
        - Stability & conflict repair matter a lot.
        - Values alignment & life direction matter a lot.
        - Trust/accountability matters a lot.
        - No shame-y options; we just learn fit.
        */

        // 1) Intent (kept light, but nudges your "serious" ecosystem)
        $makeQ(
            'intent',
            'core',
            'What are you here for right now?',
            'Choose what fits you best today (you can change later).',
            [
                ['label' => 'Serious relationship / marriage-minded', 'value' => 'marriage', 'deltas' => ['stability' => 3, 'values' => 3, 'trust' => 1]],
                ['label' => 'Serious relationship (open to marriage later)', 'value' => 'serious', 'deltas' => ['stability' => 2, 'values' => 2, 'trust' => 1]],
                ['label' => 'Dating, see where it goes', 'value' => 'dating', 'deltas' => ['values' => 0, 'trust' => 0]],
                ['label' => 'Friendship / connection', 'value' => 'friends', 'deltas' => ['trust' => 1]],
            ]
        );

        // 2) Marriage intention (dealbreaker prevention)
        $makeQ(
            'marriage_intent',
            'core',
            'Marriage intention',
            'No pressure — just clarity.',
            [
                ['label' => 'Yes, marriage is a goal', 'value' => 'yes', 'deltas' => ['values' => 4, 'stability' => 2]],
                ['label' => 'Unsure / depends on the person', 'value' => 'unsure', 'deltas' => ['values' => 1]],
                ['label' => 'No, not for me', 'value' => 'no', 'deltas' => ['values' => 0]],
            ]
        );

        // 3) Marriage timeline (modern: "depends" is acceptable)
        $makeQ(
            'marriage_timeline',
            'core',
            'If marriage is on the table, ideal timeline is…',
            'This helps avoid mismatched pacing.',
            [
                ['label' => '1–2 years', 'value' => '1-2y', 'deltas' => ['values' => 2, 'stability' => 1]],
                ['label' => '3–5 years', 'value' => '3-5y', 'deltas' => ['values' => 2, 'stability' => 1]],
                ['label' => 'Later / not rushing', 'value' => 'later', 'deltas' => ['values' => 1]],
                ['label' => 'Depends on the connection', 'value' => 'depends', 'deltas' => ['values' => 1, 'trust' => 1]],
            ]
        );

        // 4) Kids preference
        $makeQ(
            'kids_pref',
            'values',
            'Kids',
            'This is one of the biggest long-term alignment points.',
            [
                ['label' => 'Want kids', 'value' => 'want', 'deltas' => ['values' => 3]],
                ['label' => 'Don’t want kids', 'value' => 'dont', 'deltas' => ['values' => 3]],
                ['label' => 'Open / unsure', 'value' => 'open', 'deltas' => ['values' => 1]],
                ['label' => 'Already have kids', 'value' => 'have', 'deltas' => ['values' => 2, 'trust' => 1]],
            ]
        );

        // 5) Faith importance (modern: must-match is separate)
        $makeQ(
            'faith_importance',
            'values',
            'How important is faith/spirituality in your life?',
            'Not about labels — just how much it guides your life.',
            [
                ['label' => 'Very important', 'value' => '90', 'deltas' => ['values' => 2]],
                ['label' => 'Somewhat important', 'value' => '60', 'deltas' => ['values' => 1]],
                ['label' => 'Not important', 'value' => '20', 'deltas' => ['values' => 0]],
                ['label' => 'Private / personal', 'value' => '50', 'deltas' => ['values' => 1]],
            ]
        );

        // 6) Faith must match? (modern: often "no")
        $makeQ(
            'faith_must_match',
            'values',
            'Does faith need to match for long-term?',
            'Some people need this, others don’t — both are valid.',
            [
                ['label' => 'Yes, it must match', 'value' => 'yes', 'deltas' => ['values' => 2]],
                ['label' => 'Nice to have, not required', 'value' => 'maybe', 'deltas' => ['values' => 1]],
                ['label' => 'No, not important to match', 'value' => 'no', 'deltas' => ['values' => 0, 'trust' => 1]],
            ]
        );

        // 7) Stress response (attachment + emotion regulation proxy)
        $makeQ(
            'stress_response',
            'core',
            'When I’m stressed, I usually…',
            'This helps predict daily harmony and conflict style.',
            [
                ['label' => 'Talk it out and feel better', 'value' => 'talk', 'deltas' => ['stability' => 2, 'trust' => 1, 'conflict_risk' => -2]],
                ['label' => 'Need space first, then I can talk', 'value' => 'space_then_talk', 'deltas' => ['stability' => 2, 'conflict_risk' => -1]],
                ['label' => 'Get irritable / snappy (working on it)', 'value' => 'irritable', 'deltas' => ['stability' => -2, 'conflict_risk' => 3]],
                ['label' => 'Shut down / go quiet', 'value' => 'shutdown', 'deltas' => ['stability' => -1, 'conflict_risk' => 2]],
            ]
        );

        // 8) Conflict repair preference (Gottman-style repair proxy)
        $makeQ(
            'conflict_repair',
            'core',
            'After a disagreement, the best repair is…',
            'How you come back together matters more than the argument.',
            [
                ['label' => 'Apologize + talk calmly and solve it', 'value' => 'repair_solve', 'deltas' => ['stability' => 3, 'trust' => 2, 'conflict_risk' => -3]],
                ['label' => 'Take time, then return and resolve', 'value' => 'cooldown_then_resolve', 'deltas' => ['stability' => 2, 'trust' => 1, 'conflict_risk' => -2]],
                ['label' => 'Humor + affection, then talk', 'value' => 'soften_then_talk', 'deltas' => ['stability' => 2, 'conflict_risk' => -1]],
                ['label' => 'I prefer to move on without discussing much', 'value' => 'move_on', 'deltas' => ['stability' => -1, 'conflict_risk' => 2]],
            ]
        );

        // 9) Conflict escalation risk (sarcasm/stonewalling proxy)
        $makeQ(
            'conflict_escalation',
            'trust',
            'When conflict escalates, I’m most likely to…',
            'Be honest — this helps avoid painful mismatches.',
            [
                ['label' => 'Stay respectful even if I’m upset', 'value' => 'respectful', 'deltas' => ['stability' => 3, 'trust' => 2, 'conflict_risk' => -3]],
                ['label' => 'Raise my voice / get intense', 'value' => 'intense', 'deltas' => ['stability' => -1, 'conflict_risk' => 2]],
                ['label' => 'Withdraw / go silent', 'value' => 'silent', 'deltas' => ['stability' => -1, 'conflict_risk' => 2]],
                ['label' => 'Use sarcasm (working on it)', 'value' => 'sarcasm', 'deltas' => ['stability' => -2, 'trust' => -2, 'conflict_risk' => 4]],
            ]
        );

        // 10) Accountability (trust cornerstone)
        $makeQ(
            'accountability',
            'trust',
            'If I mess up, I usually…',
            'This predicts trust more than charm does.',
            [
                ['label' => 'Own it + apologize + fix it', 'value' => 'own_fix', 'deltas' => ['trust' => 5, 'stability' => 2, 'conflict_risk' => -2]],
                ['label' => 'Explain my side, then make it right', 'value' => 'explain_then_fix', 'deltas' => ['trust' => 3, 'stability' => 1, 'conflict_risk' => -1]],
                ['label' => 'Avoid it and hope it passes', 'value' => 'avoid', 'deltas' => ['trust' => -3, 'conflict_risk' => 2]],
                ['label' => 'Blame / defend first', 'value' => 'defend', 'deltas' => ['trust' => -4, 'conflict_risk' => 3]],
            ]
        );

        // 11) Reliability (trust + conscientiousness proxy)
        $makeQ(
            'reliability',
            'trust',
            'When I commit to something, I…',
            'Consistency is one of the strongest trust signals.',
            [
                ['label' => 'Follow through almost always', 'value' => 'always', 'deltas' => ['trust' => 4, 'stability' => 2]],
                ['label' => 'Usually follow through', 'value' => 'usually', 'deltas' => ['trust' => 2, 'stability' => 1]],
                ['label' => 'Sometimes struggle (depends)', 'value' => 'sometimes', 'deltas' => ['trust' => -1]],
                ['label' => 'I’m spontaneous, commitments are flexible', 'value' => 'flexible', 'deltas' => ['trust' => -2, 'values' => -1]],
            ]
        );

        // 12) Values trade-off (modern set)
        $makeQ(
            'values_tradeoff',
            'values',
            'Which matters more in a long-term relationship?',
            'Pick the one you’d protect most when life gets busy.',
            [
                ['label' => 'Growth & self-improvement', 'value' => 'growth', 'deltas' => ['values' => 3, 'stability' => 1]],
                ['label' => 'Peace & emotional safety', 'value' => 'peace', 'deltas' => ['values' => 3, 'stability' => 2, 'conflict_risk' => -1]],
                ['label' => 'Freedom & independence', 'value' => 'freedom', 'deltas' => ['values' => 2]],
                ['label' => 'Loyalty & commitment', 'value' => 'loyalty', 'deltas' => ['values' => 4, 'trust' => 1]],
            ]
        );
    }
}
