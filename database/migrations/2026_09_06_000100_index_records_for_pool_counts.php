<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two indexes for the Demome Control page, which took over a minute to open.
 *
 * The first is a correction. `idx_records_tied_wr` was built for the blocked
 * maps rule when that rule grouped by map and physics. It now groups by the
 * leaderboard too, because a map's ctf2 record is not its record, and the old
 * index stopped covering the query the moment that changed: 6.7 seconds
 * instead of 1.1.
 *
 * The second is the real cost. Three of the eight pool counts join every
 * uploaded demo to the record holding first place on its map, and there was no
 * index leading with the map, the physics and the rank. MySQL read four
 * candidate rows for each of 170 000 demos and threw nearly all of them away.
 * `Offline <= WR` alone took 22.4 seconds.
 *
 * Measured on a copy of production, 829 730 records: the eight counts together
 * went from 47 seconds to 9.3, and the whole page from 51 to 11.5. Every count
 * came back with the same number.
 *
 * `rank` is a reserved word in MySQL 8 and has to be quoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->has('idx_records_tied_wr2')) {
            DB::statement('CREATE INDEX idx_records_tied_wr2 ON records (deleted_at, mapname, physics, mode, time, mdd_id)');
        }

        if ($this->has('idx_records_tied_wr')) {
            DB::statement('DROP INDEX idx_records_tied_wr ON records');
        }

        if (! $this->has('idx_records_map_physics_rank')) {
            DB::statement('CREATE INDEX idx_records_map_physics_rank ON records (mapname, physics, `rank`, deleted_at, time)');
        }
    }

    public function down(): void
    {
        if ($this->has('idx_records_map_physics_rank')) {
            DB::statement('DROP INDEX idx_records_map_physics_rank ON records');
        }

        if (! $this->has('idx_records_tied_wr')) {
            DB::statement('CREATE INDEX idx_records_tied_wr ON records (deleted_at, mapname, physics, time, mdd_id)');
        }

        if ($this->has('idx_records_tied_wr2')) {
            DB::statement('DROP INDEX idx_records_tied_wr2 ON records');
        }
    }

    private function has(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM records'))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
