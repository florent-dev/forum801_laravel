<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Users may now belong to several guilds, once per guild
    public function up(): void
    {
        Schema::table('guild_members', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['guild_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('guild_members', function (Blueprint $table) {
            $table->dropUnique(['guild_id', 'user_id']);
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
