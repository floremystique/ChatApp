<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Prevent crash if table already exists (Railway already has it)
        if (Schema::hasTable('message_reactions')) {
            return;
        }

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 16);
            $table->timestamps();

            // optional but recommended
            $table->unique(['message_id', 'user_id', 'type']);
            $table->index('message_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
