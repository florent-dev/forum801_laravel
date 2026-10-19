<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff-only notes about a user, shown on the moderator user file
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // Moderation stats group reports by handler and handling time
        Schema::table('reports', function (Blueprint $table) {
            $table->index(['handled_by', 'handled_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['handled_by', 'handled_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
