<?php

// database/migrations/xxxx_xx_xx_add_uuid_to_chat_rooms_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
{
    Schema::table('chat_rooms', function (Blueprint $table) {
        if (!Schema::hasColumn('chat_rooms', 'uuid')) {
            $table->uuid('uuid')->nullable()->after('id');
        }
    });
}


  public function down(): void {
    Schema::table('chat_rooms', function (Blueprint $table) {
      $table->dropUnique(['uuid']);
      $table->dropColumn('uuid');
    });
  }
};
