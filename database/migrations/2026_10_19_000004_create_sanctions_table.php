<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('reason', 500);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'type', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
