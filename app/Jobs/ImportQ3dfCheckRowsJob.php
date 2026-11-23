<?php

namespace App\Jobs;

use App\Models\Map;
use App\Models\MddProfile;
use App\Models\Q3dfRecordCheck;
use App\Models\Q3dfRecordCheckRow;
use App\Services\Q3dfRecordImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Imports what a records check found: every missing record, and every record
 * where q3df holds a faster time than we do. Where we hold the faster time it
 * is left alone, because importing it would put a slower run over a better one.
 *
 * It runs as a backfill: no "your record was beaten" notifications for old
 * runs, and ratings wait for the nightly ratings:calculate.
 *
 * Two steps, status importing and then updating. Importing writes the rows.
 * Updating works out ranks for every touched map and stats for every touched
 * player, once each. Both steps stop after BUDGET_SECONDS and queue the next
 * job, which goes on where this one stopped: production runs the database
 * queue with a 1200 second retry_after, and a longer job is taken a second time.
 */
class ImportQ3dfCheckRowsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 600;

    public $tries = 1;

    private const BUDGET_SECONDS = 240;

    private float $deadline;

    public function __construct(public int $checkId)
    {
    }

    public static function importable(int $checkId)
    {
        return Q3dfRecordCheckRow::where('check_id', $checkId)
            ->whereNull('imported_at')
            ->where(fn ($q) => $q->where('kind', 'missing')
                ->orWhereColumn('q3df_time', '<', 'our_time'));
    }

    public function handle(): void
    {
        $check = Q3dfRecordCheck::find($this->checkId);

        if (! $check || ! in_array($check->status, ['importing', 'updating'], true)) {
            return;
        }

        $this->deadline = microtime(true) + self::BUDGET_SECONDS;

        try {
            $done = $check->status === 'importing'
                ? $this->importRows($check)
                : $this->updateTouched($check);

            if (CheckQ3dfRecordsJob::stopped($check->id)) {
                return;
            }

            if (! $done) {
                self::dispatch($check->id);

                return;
            }

            if ($check->status === 'importing') {
                $check->update(['status' => 'updating', 'maps_cursor' => null, 'profiles_cursor' => null]);
                self::dispatch($check->id);

                return;
            }

            $check->update(['status' => 'imported', 'finished_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            // Rows already imported keep their mark. Importing again picks up the rest.
            $check->update(['status' => 'failed', 'error' => 'Import stopped: ' . $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Q3dfRecordCheck::whereKey($this->checkId)->update([
            'status' => 'failed',
            'error' => 'Import stopped: ' . $e->getMessage(),
        ]);
    }

    /**
     * @return bool true when no importable row is left
     */
    private function importRows(Q3dfRecordCheck $check): bool
    {
        $importer = new Q3dfRecordImporter(live: false);
        $left = false;

        self::importable($check->id)->chunkById(200, function ($rows) use ($importer, $check, &$left) {
            if (CheckQ3dfRecordsJob::stopped($check->id)) {
                return false;
            }

            if (microtime(true) > $this->deadline) {
                $left = true;

                return false;
            }

            $done = [];

            foreach ($rows as $row) {
                $before = $importer->failed;
                $importer->import([$row->toApiRecord()]);

                if ($importer->failed === $before) {
                    $done[] = $row->id;
                }
            }

            Q3dfRecordCheckRow::whereIn('id', $done)->update(['imported_at' => now()]);
            Q3dfRecordCheck::whereKey($check->id)->update(['imported' => DB::raw('imported + ' . count($done))]);
        });

        return ! $left;
    }

    /**
     * @return bool true when every touched map and player is updated
     */
    private function updateTouched(Q3dfRecordCheck $check): bool
    {
        $imported = Q3dfRecordCheckRow::where('check_id', $check->id)->whereNotNull('imported_at');

        $maps = (clone $imported)
            ->when($check->maps_cursor !== null, fn ($q) => $q->where('mapname', '>', $check->maps_cursor))
            ->distinct()
            ->orderBy('mapname')
            ->pluck('mapname');

        foreach ($maps as $name) {
            if (CheckQ3dfRecordsJob::stopped($check->id)) {
                return true;
            }

            if (microtime(true) > $this->deadline) {
                return false;
            }

            $map = Map::where('name', $name)->first();

            if ($map) {
                $map->processRanks();
                $map->processAverageTime();
                RebuildDemosTopRanksJob::dispatch($map->name);
            }

            $check->update(['maps_cursor' => $name]);
        }

        $players = (clone $imported)
            ->when($check->profiles_cursor !== null, fn ($q) => $q->where('mdd_id', '>', $check->profiles_cursor))
            ->distinct()
            ->orderBy('mdd_id')
            ->pluck('mdd_id');

        foreach ($players as $mddId) {
            if (CheckQ3dfRecordsJob::stopped($check->id)) {
                return true;
            }

            if (microtime(true) > $this->deadline) {
                return false;
            }

            $profile = MddProfile::where('id', $mddId)->first();

            if ($profile) {
                $profile->processStats();
            } else {
                ScrapeProfile::dispatch($mddId);
            }

            $check->update(['profiles_cursor' => $mddId]);
        }

        return true;
    }
}
