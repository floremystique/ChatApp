<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        if (!Schema::hasColumn('messages', 'client_message_id')) {
            return;
        }

        // Goal: make (chat_room_id, user_id, client_message_id) unique to guarantee idempotency
        // under retries (common on mobile/offline).
        //
        // Wrapped in try/catch because environments may already have the index
        // or historical duplicates might exist and need cleanup first.
        try {
            DB::statement('ALTER TABLE `messages` DROP INDEX `messages_room_client_idx`');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::statement('ALTER TABLE `messages` ADD UNIQUE INDEX `messages_room_user_client_uq` (`chat_room_id`, `user_id`, `client_message_id`)');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `messages` DROP INDEX `messages_room_user_client_uq`');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::statement('ALTER TABLE `messages` ADD INDEX `messages_room_client_idx` (`chat_room_id`, `client_message_id`)');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
