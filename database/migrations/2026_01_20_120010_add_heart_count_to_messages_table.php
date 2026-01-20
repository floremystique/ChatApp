<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    // If table doesn't exist (fresh DB), create it
    if (!Schema::hasTable('message_reactions')) {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 16);
            $table->timestamps();
        });
    }

    // If it already exists (Railway), just ensure constraints/indexes exist
    Schema::table('message_reactions', function (Blueprint $table) {
        // Add indexes if missing (MySQL doesn't have a clean "if missing" API,
        // so keep these consistent with your existing migrations to avoid duplicates)

        // Recommended indexes/constraints:
        // 1) Prevent duplicate same reaction by same user on same message
        // 2) Speed queries by message_id
        // NOTE: If these already exist, this will fail, so only add if you know they're missing.
    });
}

};
