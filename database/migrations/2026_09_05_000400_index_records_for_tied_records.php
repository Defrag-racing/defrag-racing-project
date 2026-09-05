<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An index for the one question the blocked maps page asks.
 *
 * "How many people hold this map's record time" reads every one of 830 000
 * records twice: once to find the best time per map and physics, once to count
 * who is on it. Measured at 13.1 seconds, which the page paid on every cache
 * miss. With this index the same answer takes 0.47 seconds.
 *
 * The columns are in the order the query needs them: the filter first, then
 * what it groups by, then the time it takes the minimum of, then the player it
 * counts. MySQL answers the whole thing from the index and never opens a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Already built by hand while this was measured, so a plain create
        // would fail on the machine it was measured on.
        if ($this->exists()) {
            return;
        }

        DB::statement('CREATE INDEX idx_records_tied_wr ON records (deleted_at, mapname, physics, time, mdd_id)');
    }

    public function down(): void
    {
        if ($this->exists()) {
            DB::statement('DROP INDEX idx_records_tied_wr ON records');
        }
    }

    private function exists(): bool
    {
        return collect(DB::select('SHOW INDEX FROM records'))
            ->contains(fn ($row) => $row->Key_name === 'idx_records_tied_wr');
    }
};
