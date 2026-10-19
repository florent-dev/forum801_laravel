<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The interface language becomes a user setting; the browsed community is no longer stored
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 10)->nullable());
        foreach (['en' => 'en', 'fr' => 'fr', 'es' => 'es', 'br' => 'pt_BR', 'de' => 'de', 'pl' => 'pl', 'ru' => 'ru', 'tr' => 'tr', 'nl' => 'nl', 'it' => 'it', 'hu' => 'hu', 'ro' => 'ro'] as $community => $locale) {
            DB::table('users')->where('community', $community)->update(['locale' => $locale]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('community'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('community', 5)->default('en'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
