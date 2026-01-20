<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'heart_count')) {
                $table->unsignedInteger('heart_count')->default(0)->after('body');
                $table->index(['chat_room_id', 'heart_count']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'heart_count')) {
                $table->dropColumn('heart_count');
            }
        });
    }
};
