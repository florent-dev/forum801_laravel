<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('community', 5)->default('en')->after('slug');
            $table->index(['community', 'position']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('community', 5)->default('en');
        });

        // Former free-form flags become communities (gb was English)
        DB::table('sections')->whereNotNull('flag')->where('flag', '!=', 'gb')->update(['community' => DB::raw('flag')]);
        Schema::table('sections', fn (Blueprint $table) => $table->dropColumn('flag'));
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('flag', 20)->nullable();
            $table->dropIndex(['community', 'position']);
            $table->dropColumn('community');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('community'));
    }
};
