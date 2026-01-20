<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            try { $table->index(['chat_room_id','id'], 'messages_room_id_id_idx'); } catch (\Throwable $e) {}
            try { $table->index(['chat_room_id','read_at'], 'messages_room_read_idx'); } catch (\Throwable $e) {}
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('chat_rooms', 'user_low') && Schema::hasColumn('chat_rooms', 'user_high')) {
                try { $table->index(['user_low','user_high'], 'chat_rooms_low_high_idx'); } catch (\Throwable $e) {}
            }
        });

        if (Schema::hasTable('user_chat_stats')) {
            Schema::table('user_chat_stats', function (Blueprint $table) {
                try { $table->index('user_id', 'user_chat_stats_user_idx'); } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        // Usually safe to keep indexes.
    }
};
