<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // e.g. points_per_shared_tag
            $table->integer('value');                 // e.g. 2
            $table->string('label')->nullable();      // Human text label (optional)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_scoring_rules');
    }
};
