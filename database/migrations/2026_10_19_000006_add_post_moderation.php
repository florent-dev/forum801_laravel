<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Moderated posts stay visible with a public note
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderation_reason', 500)->nullable();
            // Hidden posts keep who hid them and why
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hide_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn(['moderated_at', 'moderation_reason', 'hide_reason']);
        });
    }
};
