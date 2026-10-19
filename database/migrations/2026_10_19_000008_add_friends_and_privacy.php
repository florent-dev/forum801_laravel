<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('hide_online')->default(false);
            $table->string('mention_policy', 10)->default('everyone');
        });

        // One row per request; accepted rows make both users friends
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'friend_id']);
            $table->index(['friend_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['hide_online', 'mention_policy']));
    }
};
