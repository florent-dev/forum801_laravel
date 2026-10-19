<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon')->nullable();
            $table->string('flag', 20)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['visibility', 'position']);
        });

        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->foreignId('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();
            $table->index(['section_id', 'updated_at']);
            $table->index(['section_id', 'is_pinned', 'last_post_at']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->text('body_html');
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamps();
            $table->unique(['post_id', 'user_id', 'type']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 1000);
            $table->string('status', 20)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('scope', 20)->default('global');
            $table->string('color', 7)->default('#009D9D');
            $table->unsignedInteger('rank')->default(0);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type')->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();
            $table->unique(['role_id', 'user_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        Schema::create('thread_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_post_id')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->primary(['user_id', 'thread_id']);
        });

        // Full-text search vectors are PostgreSQL-only generated columns
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE threads ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(title, ''))) STORED");
            DB::statement('CREATE INDEX threads_search_vector_idx ON threads USING GIN (search_vector)');
            DB::statement("ALTER TABLE posts ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(body, ''))) STORED");
            DB::statement('CREATE INDEX posts_search_vector_idx ON posts USING GIN (search_vector)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_reads');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('threads');
        Schema::dropIfExists('sections');
    }
};
