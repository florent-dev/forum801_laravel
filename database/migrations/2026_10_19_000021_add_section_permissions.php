<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            // Role names (plus "guest") denied each right; empty means every role has it
            $table->jsonb('denied_view_roles')->default('[]');
            $table->jsonb('denied_thread_roles')->default('[]');
            // Archived sections are read-only for everyone but their moderators
            $table->boolean('is_archived')->default(false);
            $table->boolean('polls_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['denied_view_roles', 'denied_thread_roles', 'is_archived', 'polls_enabled']);
        });
    }
};
