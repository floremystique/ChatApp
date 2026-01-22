<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatchStaticSeeder extends Seeder
{
    public function run(): void
    {
        // Score bands
        $bands = [
            ['min_score'=>0,'label'=>'Light match','description'=>'Worth a try'],
            ['min_score'=>3,'label'=>'Good match','description'=>'Some shared interests'],
            ['min_score'=>5,'label'=>'Strong match','description'=>'Good conversation potential'],
            ['min_score'=>7,'label'=>'Excellent match','description'=>'Very high compatibility'],
        ];

        foreach ($bands as $b) {
            DB::table('match_score_bands')->updateOrInsert(
                ['min_score' => $b['min_score']],
                ['label'=>$b['label'],'description'=>$b['description'],'is_active'=>1,'updated_at'=>now(),'created_at'=>now()]
            );
        }

        // Scoring rules
        $rules = [
            ['rule_key'=>'points_per_shared_tag','value'=>2,'label'=>'Points per shared tag'],
            ['rule_key'=>'points_same_mode','value'=>2,'label'=>'Bonus if same conversation mode'],
            ['rule_key'=>'points_looks_alignment','value'=>1,'label'=>'Bonus if looks preference matches'],
        ];

        foreach ($rules as $r) {
            DB::table('match_scoring_rules')->updateOrInsert(
                ['rule_key'=>$r['rule_key']],
                ['value'=>$r['value'],'label'=>$r['label'],'is_active'=>1,'updated_at'=>now(),'created_at'=>now()]
            );
        }

        // Questions (key must be unique)
        $questions = [
            ['key'=>'intent','category'=>'core','ui_type'=>'single','title'=>'What are you here for right now?','subtitle'=>'Choose what fits you best today (you can change later).','order'=>1],
            ['key'=>'marriage_intent','category'=>'core','ui_type'=>'single','title'=>'Marriage intention','subtitle'=>'No pressure — just clarity.','order'=>2],
            ['key'=>'marriage_timeline','category'=>'core','ui_type'=>'single','title'=>'If marriage is on the table, ideal timeline is…','subtitle'=>'This helps avoid mismatched pacing.','order'=>3],
            ['key'=>'kids_pref','category'=>'values','ui_type'=>'single','title'=>'Kids','subtitle'=>'This is one of the biggest long-term alignment points.','order'=>4],
            ['key'=>'faith_importance','category'=>'values','ui_type'=>'single','title'=>'How important is faith/spirituality in your life?','subtitle'=>'Not about labels — just how much it guides your life.','order'=>5],
            ['key'=>'faith_must_match','category'=>'values','ui_type'=>'single','title'=>'Does faith need to match for long-term?','subtitle'=>'Some people need this, others don’t — both are valid.','order'=>6],
            ['key'=>'stress_response','category'=>'core','ui_type'=>'single','title'=>'When I’m stressed, I usually…','subtitle'=>'This helps predict daily harmony and conflict style.','order'=>7],
            ['key'=>'conflict_repair','category'=>'core','ui_type'=>'single','title'=>'After a disagreement, the best repair is…','subtitle'=>'How you come back together matters more than the argument.','order'=>8],
            ['key'=>'conflict_escalation','category'=>'trust','ui_type'=>'single','title'=>'When conflict escalates, I’m most likely to…','subtitle'=>'Be honest — this helps avoid painful mismatches.','order'=>9],
            ['key'=>'accountability','category'=>'trust','ui_type'=>'single','title'=>'If I mess up, I usually…','subtitle'=>'This predicts trust more than charm does.','order'=>10],
            ['key'=>'reliability','category'=>'trust','ui_type'=>'single','title'=>'When I commit to something, I…','subtitle'=>'Consistency is one of the strongest trust signals.','order'=>11],
            ['key'=>'values_tradeoff','category'=>'values','ui_type'=>'single','title'=>'Which matters more in a long-term relationship?','subtitle'=>'Pick the one you’d protect most when life gets busy.','order'=>12],
        ];

        foreach ($questions as $q) {
            DB::table('match_questions')->updateOrInsert(
                ['key'=>$q['key']],
                [
                    'category'=>$q['category'],
                    'ui_type'=>$q['ui_type'],
                    'title'=>$q['title'],
                    'subtitle'=>$q['subtitle'],
                    'is_active'=>1,
                    'order'=>$q['order'],
                    'updated_at'=>now(),
                    'created_at'=>now()
                ]
            );
        }

        // Build question_id map
        $qid = DB::table('match_questions')->pluck('id','key');

        // Options
        $options = [
            'intent' => [
                ['label'=>'Serious relationship / marriage-minded','value'=>'marriage','order'=>1,'feature_deltas'=>['stability'=>3,'values'=>3,'trust'=>1]],
                ['label'=>'Serious relationship (open to marriage later)','value'=>'serious','order'=>2,'feature_deltas'=>['stability'=>2,'values'=>2,'trust'=>1]],
                ['label'=>'Dating, see where it goes','value'=>'dating','order'=>3,'feature_deltas'=>['values'=>0,'trust'=>0]],
                ['label'=>'Friendship / connection','value'=>'friends','order'=>4,'feature_deltas'=>['trust'=>1]],
            ],
            'marriage_intent' => [
                ['label'=>'Yes, marriage is a goal','value'=>'yes','order'=>1,'feature_deltas'=>['values'=>4,'stability'=>2]],
                ['label'=>'Unsure / depends on the person','value'=>'unsure','order'=>2,'feature_deltas'=>['values'=>1]],
                ['label'=>'No, not for me','value'=>'no','order'=>3,'feature_deltas'=>['values'=>0]],
            ],
            'marriage_timeline' => [
                ['label'=>'1–2 years','value'=>'1-2y','order'=>1,'feature_deltas'=>['values'=>2,'stability'=>1]],
                ['label'=>'3–5 years','value'=>'3-5y','order'=>2,'feature_deltas'=>['values'=>2,'stability'=>1]],
                ['label'=>'Later / not rushing','value'=>'later','order'=>3,'feature_deltas'=>['values'=>1]],
                ['label'=>'Depends on the connection','value'=>'depends','order'=>4,'feature_deltas'=>['values'=>1,'trust'=>1]],
            ],
            'kids_pref' => [
                ['label'=>'Want kids','value'=>'want','order'=>1,'feature_deltas'=>['values'=>3]],
                ['label'=>'Don’t want kids','value'=>'dont','order'=>2,'feature_deltas'=>['values'=>3]],
                ['label'=>'Open / unsure','value'=>'open','order'=>3,'feature_deltas'=>['values'=>1]],
                ['label'=>'Already have kids','value'=>'have','order'=>4,'feature_deltas'=>['values'=>2,'trust'=>1]],
            ],
            'faith_importance' => [
                ['label'=>'Very important','value'=>'90','order'=>1,'feature_deltas'=>['values'=>2]],
                ['label'=>'Somewhat important','value'=>'60','order'=>2,'feature_deltas'=>['values'=>1]],
                ['label'=>'Not important','value'=>'20','order'=>3,'feature_deltas'=>['values'=>0]],
                ['label'=>'Private / personal','value'=>'50','order'=>4,'feature_deltas'=>['values'=>1]],
            ],
            'faith_must_match' => [
                ['label'=>'Yes, it must match','value'=>'yes','order'=>1,'feature_deltas'=>['values'=>2]],
                ['label'=>'Nice to have, not required','value'=>'maybe','order'=>2,'feature_deltas'=>['values'=>1]],
                ['label'=>'No, not important to match','value'=>'no','order'=>3,'feature_deltas'=>['values'=>0,'trust'=>1]],
            ],
            'stress_response' => [
                ['label'=>'Talk it out and feel better','value'=>'talk','order'=>1,'feature_deltas'=>['stability'=>2,'trust'=>1,'conflict_risk'=>-2]],
                ['label'=>'Need space first, then I can talk','value'=>'space_then_talk','order'=>2,'feature_deltas'=>['stability'=>2,'conflict_risk'=>-1]],
                ['label'=>'Get irritable / snappy (working on it)','value'=>'irritable','order'=>3,'feature_deltas'=>['stability'=>-2,'conflict_risk'=>3]],
                ['label'=>'Shut down / go quiet','value'=>'shutdown','order'=>4,'feature_deltas'=>['stability'=>-1,'conflict_risk'=>2]],
            ],
            'conflict_repair' => [
                ['label'=>'Apologize + talk calmly and solve it','value'=>'repair_solve','order'=>1,'feature_deltas'=>['stability'=>3,'trust'=>2,'conflict_risk'=>-3]],
                ['label'=>'Take time, then return and resolve','value'=>'cooldown_then_resolve','order'=>2,'feature_deltas'=>['stability'=>2,'trust'=>1,'conflict_risk'=>-2]],
                ['label'=>'Humor + affection, then talk','value'=>'soften_then_talk','order'=>3,'feature_deltas'=>['stability'=>2,'conflict_risk'=>-1]],
                ['label'=>'I prefer to move on without discussing much','value'=>'move_on','order'=>4,'feature_deltas'=>['stability'=>-1,'conflict_risk'=>2]],
            ],
            'conflict_escalation' => [
                ['label'=>'Stay respectful even if I’m upset','value'=>'respectful','order'=>1,'feature_deltas'=>['stability'=>3,'trust'=>2,'conflict_risk'=>-3]],
                ['label'=>'Raise my voice / get intense','value'=>'intense','order'=>2,'feature_deltas'=>['stability'=>-1,'conflict_risk'=>2]],
                ['label'=>'Withdraw / go silent','value'=>'silent','order'=>3,'feature_deltas'=>['stability'=>-1,'conflict_risk'=>2]],
                ['label'=>'Use sarcasm (working on it)','value'=>'sarcasm','order'=>4,'feature_deltas'=>['stability'=>-2,'trust'=>-2,'conflict_risk'=>4]],
            ],
            'accountability' => [
                ['label'=>'Own it + apologize + fix it','value'=>'own_fix','order'=>1,'feature_deltas'=>['trust'=>5,'stability'=>2,'conflict_risk'=>-2]],
                ['label'=>'Explain my side, then make it right','value'=>'explain_then_fix','order'=>2,'feature_deltas'=>['trust'=>3,'stability'=>1,'conflict_risk'=>-1]],
                ['label'=>'Avoid it and hope it passes','value'=>'avoid','order'=>3,'feature_deltas'=>['trust'=>-3,'conflict_risk'=>2]],
                ['label'=>'Blame / defend first','value'=>'defend','order'=>4,'feature_deltas'=>['trust'=>-4,'conflict_risk'=>3]],
            ],
            'reliability' => [
                ['label'=>'Follow through almost always','value'=>'always','order'=>1,'feature_deltas'=>['trust'=>4,'stability'=>2]],
                ['label'=>'Usually follow through','value'=>'usually','order'=>2,'feature_deltas'=>['trust'=>2,'stability'=>1]],
                ['label'=>'Sometimes struggle (depends)','value'=>'sometimes','order'=>3,'feature_deltas'=>['trust'=>-1]],
                ['label'=>'I’m spontaneous, commitments are flexible','value'=>'flexible','order'=>4,'feature_deltas'=>['trust'=>-2,'values'=>-1]],
            ],
            'values_tradeoff' => [
                ['label'=>'Growth & self-improvement','value'=>'growth','order'=>1,'feature_deltas'=>['values'=>3,'stability'=>1]],
                ['label'=>'Peace & emotional safety','value'=>'peace','order'=>2,'feature_deltas'=>['values'=>3,'stability'=>2,'conflict_risk'=>-1]],
                ['label'=>'Freedom & independence','value'=>'freedom','order'=>3,'feature_deltas'=>['values'=>2]],
                ['label'=>'Loyalty & commitment','value'=>'loyalty','order'=>4,'feature_deltas'=>['values'=>4,'trust'=>1]],
            ],
        ];

        foreach ($options as $questionKey => $opts) {
            $questionId = $qid[$questionKey] ?? null;
            if (!$questionId) continue;

            foreach ($opts as $o) {
                DB::table('match_question_options')->updateOrInsert(
                    ['match_question_id'=>$questionId, 'value'=>$o['value']],
                    [
                        'label'=>$o['label'],
                        'order'=>$o['order'],
                        'feature_deltas'=>json_encode($o['feature_deltas']),
                        'updated_at'=>now(),
                        'created_at'=>now(),
                    ]
                );
            }
        }
    }
}
