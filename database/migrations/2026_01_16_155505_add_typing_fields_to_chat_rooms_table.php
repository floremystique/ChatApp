<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->timestamp('user_one_typing_until')->nullable();
            $table->timestamp('user_two_typing_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn(['user_one_typing_until','user_two_typing_until']);
        });
    }
};
