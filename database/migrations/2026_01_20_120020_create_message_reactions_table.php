<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // e.g. 'heart'
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'type'], 'msg_reactions_unique');
            $table->index(['message_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
