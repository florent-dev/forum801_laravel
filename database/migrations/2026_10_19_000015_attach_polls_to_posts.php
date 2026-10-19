<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('max_choices')->nullable();
            // always, after_vote or after_close
            $table->string('results_visibility', 12)->default('always');
            $table->boolean('show_voters')->default(false);
            $table->boolean('allow_change')->default(true);
            // A thread can now hold several polls, one per post
            $table->dropUnique(['thread_id']);
            $table->index('thread_id');
        });

        // Existing thread polls move onto the first post of their thread
        foreach (DB::table('polls')->whereNull('post_id')->get(['id', 'thread_id']) as $poll) {
            $postId = DB::table('posts')->where('thread_id', $poll->thread_id)->orderBy('id')->value('id');
            DB::table('polls')->where('id', $poll->id)->update(['post_id' => $postId]);
        }
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropIndex(['thread_id']);
            $table->unique('thread_id');
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn(['max_choices', 'results_visibility', 'show_voters', 'allow_change']);
        });
    }
};
