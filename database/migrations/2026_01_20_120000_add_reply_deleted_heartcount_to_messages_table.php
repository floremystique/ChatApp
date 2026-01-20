<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'reply_to_id')) {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('user_id');
                $table->foreign('reply_to_id')->references('id')->on('messages')->nullOnDelete();
                $table->index(['chat_room_id', 'reply_to_id']);
            }

            if (!Schema::hasColumn('messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('read_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
                $table->index(['chat_room_id', 'deleted_at']);
            }

            if (!Schema::hasColumn('messages', 'heart_count')) {
                $table->unsignedInteger('heart_count')->default(0)->after('body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reply_to_id')) {
                $table->dropForeign(['reply_to_id']);
                $table->dropColumn('reply_to_id');
            }

            if (Schema::hasColumn('messages', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            }
            if (Schema::hasColumn('messages', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }

            if (Schema::hasColumn('messages', 'heart_count')) {
                $table->dropColumn('heart_count');
            }
        });
    }
};
