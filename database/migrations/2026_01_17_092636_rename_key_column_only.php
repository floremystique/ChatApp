<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_scoring_rules', function ($table) {
            $table->renameColumn('key', 'rule_key');
        });
    }

    public function down(): void
    {
        Schema::table('match_scoring_rules', function ($table) {
            $table->renameColumn('rule_key', 'key');
        });
    }
};
