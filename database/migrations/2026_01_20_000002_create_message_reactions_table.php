<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20);
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['message_id','type'], 'msg_reactions_msg_type_idx');
            $table->unique(['message_id','user_id','type'], 'msg_reactions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
