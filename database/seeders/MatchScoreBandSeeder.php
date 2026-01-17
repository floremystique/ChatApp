<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatchScoreBandSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('match_score_bands')->truncate();

        DB::table('match_score_bands')->insert([
            ['min_score' => 0, 'label' => 'Light match',     'description' => 'Worth a try',                 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['min_score' => 3, 'label' => 'Good match',      'description' => 'Some shared interests',       'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['min_score' => 5, 'label' => 'Strong match',    'description' => 'Good conversation potential', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['min_score' => 7, 'label' => 'Excellent match', 'description' => 'Very high compatibility',     'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
