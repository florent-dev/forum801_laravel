<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One flag per page tour instead of a single one
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tour_completed_at');
            $table->json('tours_seen')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tours_seen');
            $table->timestamp('tour_completed_at')->nullable();
        });
    }
};
