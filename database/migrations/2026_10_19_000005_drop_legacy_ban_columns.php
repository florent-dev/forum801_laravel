<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Suspensions now live in the sanctions table
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['banned_until', 'ban_reason']));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_until')->nullable();
            $table->string('ban_reason', 500)->nullable();
        });
    }
};
