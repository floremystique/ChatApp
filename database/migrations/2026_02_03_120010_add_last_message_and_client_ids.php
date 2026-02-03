<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_rooms', 'last_message_id')) {
                $table->unsignedBigInteger('last_message_id')->nullable()->after('user_two');
                $table->index('last_message_id', 'chat_rooms_last_message_idx');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'client_message_id')) {
                $table->string('client_message_id', 64)->nullable()->after('user_id');
                $table->index(['chat_room_id', 'client_message_id'], 'messages_room_client_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Keeping columns on down is usually safe, but include drops if needed.
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            // Keeping columns on down is usually safe.
        });
    }
};
