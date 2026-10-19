<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_filters', function (Blueprint $table) {
            $table->id();
            $table->string('phrase', 100);
            // block: refuse the text, replace: mask it, review: publish and report to moderators
            $table->string('action', 10)->default('replace');
            $table->boolean('whole_word')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('phrase');
        });

        // Reports raised by the word filter have no human reporter
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('reporter_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_filters');
    }
};
