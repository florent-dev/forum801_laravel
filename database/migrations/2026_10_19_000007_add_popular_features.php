<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->string('prefix', 20)->nullable();
            $table->foreignId('solution_post_id')->nullable()->constrained('posts')->nullOnDelete();
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('question', 200);
            $table->boolean('multiple')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['poll_option_id', 'user_id']);
            $table->index(['poll_id', 'user_id']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'post_id']);
        });

        Schema::create('user_ignores', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ignored_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'ignored_id']);
        });

        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['post_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
        Schema::dropIfExists('user_ignores');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
        Schema::table('threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solution_post_id');
            $table->dropColumn('prefix');
        });
    }
};
