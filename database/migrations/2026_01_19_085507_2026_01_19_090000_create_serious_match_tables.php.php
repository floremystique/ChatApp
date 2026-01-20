<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_questions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // e.g. "marriage_intent"
            $table->string('category');                   // "core", "values", "trust", "lifestyle", "fun"
            $table->string('ui_type');                    // "single", "multi"
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('match_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_question_id')->constrained('match_questions')->cascadeOnDelete();
            $table->string('label');
            $table->string('value')->nullable();          // stored value (string)
            $table->unsignedSmallInteger('order')->default(0);

            // trait updates (compact + changeable without code)
            // Example: {"stability":2,"conflict_risk":-2,"trust":1}
            $table->json('feature_deltas')->nullable();

            $table->timestamps();
        });

        Schema::create('user_match_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_question_id')->constrained('match_questions')->cascadeOnDelete();
            $table->foreignId('match_question_option_id')->nullable()
                ->constrained('match_question_options')->nullOnDelete();
            $table->json('answer_payload')->nullable();   // for multi
            $table->timestamps();
            $table->unique(['user_id','match_question_id']);
        });

        Schema::create('user_match_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // 0..100
            $table->unsignedTinyInteger('stability_score')->default(50);
            $table->unsignedTinyInteger('values_score')->default(50);
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->unsignedTinyInteger('conflict_risk')->default(50);

            // direction
            $table->string('intent')->nullable();           // friends/dating/serious/marriage
            $table->string('marriage_intent')->nullable();  // yes/no/unsure
            $table->string('marriage_timeline')->nullable();// 1-2y/3-5y/later/depends
            $table->string('kids_pref')->nullable();        // want/dont/open/have
            $table->unsignedTinyInteger('faith_importance')->nullable(); // 0..100
            $table->boolean('faith_must_match')->default(false);

            // optional vibe labels
            $table->string('style_label')->nullable();      // e.g. "Quiet Planner (INFJ vibe)"
            $table->boolean('astrology_on')->default(false);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->index(['marriage_intent']);
            $table->index(['kids_pref']);
        });

        Schema::create('user_chat_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('reply_time_ema_sec')->default(0);
            $table->unsignedInteger('replies_count')->default(0);

            $table->unsignedTinyInteger('reply_within_1h')->default(0);   // %
            $table->unsignedTinyInteger('reply_within_24h')->default(0);  // %

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chat_stats');
        Schema::dropIfExists('user_match_features');
        Schema::dropIfExists('user_match_answers');
        Schema::dropIfExists('match_question_options');
        Schema::dropIfExists('match_questions');
    }
};
