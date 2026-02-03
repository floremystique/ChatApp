<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Highest message id this user has seen in this room.
            $table->unsignedBigInteger('last_read_message_id')->nullable()->index();

            // Cheap O(1) unread counts for chat list.
            $table->unsignedInteger('unread_count')->default(0);

            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->unique(['chat_room_id', 'user_id']);
            $table->index(['user_id', 'chat_room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_reads');
    }
};
