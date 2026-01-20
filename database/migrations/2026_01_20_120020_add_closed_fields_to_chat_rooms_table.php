<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_rooms', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('user_two_typing_until');
            }
            if (!Schema::hasColumn('chat_rooms', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
                $table->index(['closed_at','closed_by']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('chat_rooms', 'closed_by')) {
                $table->dropForeign(['closed_by']);
                $table->dropColumn('closed_by');
            }
            if (Schema::hasColumn('chat_rooms', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
