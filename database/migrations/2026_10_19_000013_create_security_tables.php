<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_ip', 45)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->index('last_ip');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('ip', 45)->nullable();
        });

        // Every address an account used, for moderation and alt-account detection
        Schema::create('user_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unique(['user_id', 'ip']);
            $table->index(['ip', 'last_seen_at']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('severity', 10)->default('info');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['type', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['severity', 'created_at']);
        });

        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            // A single address or a CIDR range such as 203.0.113.0/24
            $table->string('ip', 64);
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_bans');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('user_ips');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('ip'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_ip']);
            $table->dropColumn(['registration_ip', 'last_ip']);
        });
    }
};
