<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guilds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('slug', 60)->unique();
            $table->string('tag', 6);
            $table->string('community', 5)->default('en');
            $table->string('emblem')->nullable();
            $table->text('description')->nullable();
            $table->text('description_html')->nullable();
            // open: join instantly, application: officers review, closed: nobody joins
            $table->string('recruitment', 12)->default('application');
            $table->boolean('forum_public')->default(false);
            $table->timestamps();
            $table->index(['community', 'recruitment']);
        });

        Schema::create('guild_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            // A user belongs to one guild at a time
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member');
            $table->timestamps();
            $table->index(['guild_id', 'role']);
        });

        Schema::create('guild_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message', 1000)->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['guild_id', 'status']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('guild_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sections', fn (Blueprint $table) => $table->dropConstrainedForeignId('guild_id'));
        Schema::dropIfExists('guild_applications');
        Schema::dropIfExists('guild_members');
        Schema::dropIfExists('guilds');
    }
};
