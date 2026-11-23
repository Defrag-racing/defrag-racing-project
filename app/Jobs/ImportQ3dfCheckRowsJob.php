<?php

namespace App\Jobs;

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
 * runs, each touched map and profile is updated once at the end, and ratings
 * wait for the nightly ratings:calculate.
 */
class ImportQ3dfCheckRowsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 7200;

    public $tries = 1;

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

        if (! $check) {
            return;
        }

        $check->update(['status' => 'importing', 'error' => null]);

        $importer = new Q3dfRecordImporter(live: false);

        try {
            self::importable($check->id)->chunkById(500, function ($rows) use ($importer, $check) {
                if (CheckQ3dfRecordsJob::stopped($check->id)) {
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

            $importer->finish();

            if (! CheckQ3dfRecordsJob::stopped($check->id)) {
                $check->update(['status' => 'imported', 'finished_at' => now()]);
            }
        } catch (\Throwable $e) {
            // Rows already imported keep their mark. Starting again picks up the rest.
            $importer->finish();
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
}
