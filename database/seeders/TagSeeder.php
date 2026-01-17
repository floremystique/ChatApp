<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tag;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Casual', 'Friendly', 'Deep', 'Study', 'Inspiring', 'Matchmaking',
            'Supportive', 'Artistic', 'Funny', 'Anime', 'Gaming', 'Business',
        ];

        foreach ($tags as $t) {
            Tag::firstOrCreate(['name' => $t]);
        }
    }
}