<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_score_bands', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_score')->unique(); // 0,3,5,7...
            $table->string('label');                        // "Strong match"
            $table->string('description');                  // "Good conversation potential"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_score_bands');
    }
};
