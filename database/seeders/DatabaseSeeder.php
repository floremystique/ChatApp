<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\TagSeeder;
use Database\Seeders\MatchStaticSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TagSeeder::class,
            MatchStaticSeeder::class,
        ]);
    }
}
