<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['threads', 'posts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['threads', 'posts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('deleted_by');
                $table->dropSoftDeletes();
            });
        }
    }
};
