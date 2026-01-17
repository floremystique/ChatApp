<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatchScoringRulesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('match_scoring_rules')->truncate();

        DB::table('match_scoring_rules')->insert([
            [
                'key' => 'points_per_shared_tag',
                'value' => 2,
                'label' => 'Points per shared tag',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_same_mode',
                'value' => 2,
                'label' => 'Bonus if same conversation mode',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_looks_alignment',
                'value' => 1,
                'label' => 'Bonus if looks preference matches',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
