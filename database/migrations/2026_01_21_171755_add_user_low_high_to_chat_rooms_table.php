<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            // Nullable first so existing rows don’t break
            $table->unsignedBigInteger('user_low')->nullable()->after('user_two');
            $table->unsignedBigInteger('user_high')->nullable()->after('user_low');
        });

        // Backfill existing data
        DB::statement("
            UPDATE chat_rooms
            SET
                user_low  = LEAST(user_one, user_two),
                user_high = GREATEST(user_one, user_two)
            WHERE user_low IS NULL OR user_high IS NULL
        ");

        Schema::table('chat_rooms', function (Blueprint $table) {
            // Enforce uniqueness for the pair
            $table->unique(['user_low', 'user_high'], 'chat_rooms_user_pair_unique');

            // Optional but recommended for performance
            $table->index(['user_low']);
            $table->index(['user_high']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropUnique('chat_rooms_user_pair_unique');
            $table->dropIndex(['user_low']);
            $table->dropIndex(['user_high']);
            $table->dropColumn(['user_low', 'user_high']);
        });
    }
};
