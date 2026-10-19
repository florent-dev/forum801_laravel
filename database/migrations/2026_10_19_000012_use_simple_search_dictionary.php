<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The forum is multilingual, so search vectors use the language-neutral dictionary
    public function up(): void
    {
        $this->rebuild('simple');
    }

    public function down(): void
    {
        $this->rebuild('english');
    }

    protected function rebuild(string $dictionary): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (['threads' => 'title', 'posts' => 'body'] as $table => $column) {
            DB::statement("DROP INDEX IF EXISTS {$table}_search_vector_idx");
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS search_vector");
            DB::statement("ALTER TABLE {$table} ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('{$dictionary}', coalesce({$column}, ''))) STORED");
            DB::statement("CREATE INDEX {$table}_search_vector_idx ON {$table} USING GIN (search_vector)");
        }
    }
};
