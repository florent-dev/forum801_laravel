<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_until')->nullable();
            $table->string('ban_reason', 500)->nullable();
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0);
        });

        Schema::create('thread_subscriptions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'thread_id']);
            $table->index('thread_id');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->primary(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->text('body_html');
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('thread_subscriptions');
        Schema::table('threads', fn (Blueprint $table) => $table->dropColumn('views_count'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['banned_until', 'ban_reason']));
    }
};
