<?php

namespace App\Services;

use App\Http\Controllers\RecordsController;
use App\Jobs\ProcessNotificationsJob;
use App\Jobs\RebuildDemosTopRanksJob;
use App\Jobs\RecalcMapRatingsJob;
use App\Jobs\ScrapeProfile;
use App\Models\Map;
use App\Models\MddProfile;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Puts q3df records into the records table. One record is one personal best:
 * a new time moves the old row into record_histories and takes its place.
 *
 * Two ways to run it.
 *
 * Live (the minute job): every new record updates its map at once, goes to the
 * top of the records page and tells the players it beat.
 *
 * Backfill (the records check in the admin): the records are old. Telling a
 * player today that somebody beat him in 2019 is wrong, and putting a 2019 run
 * at the top of the latest records is wrong too. So a backfill only writes the
 * rows. ImportQ3dfCheckRowsJob updates each touched map and profile one time
 * afterwards. Ratings are left to the nightly ratings:calculate. One recalc
 * job per touched leaderboard was 7,600 jobs for a 10,000 record import, and
 * every new record waited behind them in the same queue.
 */
class Q3dfRecordImporter
{
    public int $inserted = 0;

    public int $updated = 0;

    public int $duplicates = 0;

    public int $failed = 0;

    public function __construct(private bool $live = true)
    {
    }

    /**
     * One bad record is logged and skipped, so it cannot stop the rest.
     */
    public function import(array $records): void
    {
        foreach ($records as $record) {
            try {
                $this->importOne($record);
            } catch (\Throwable $e) {
                $this->failed++;
                Log::warning('Q3dfRecordImporter: skipped a record after an error', [
                    'mdd_id' => $record['mdd_id'] ?? null,
                    'map' => $record['map'] ?? null,
                    'physics' => $record['physics'] ?? null,
                    'mode' => $record['mode'] ?? null,
                    'time' => $record['time'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return string inserted, updated or duplicate
     */
    public function importOne(array $record): string
    {
        $find = Record::query()
            ->where('physics', $record['physics'])
            ->where('mode', $record['mode'])
            ->where('mdd_id', $record['mdd_id'])
            ->where('mapname', $record['map'])
            ->first();

        if (! $find) {
            $this->insertRecord($record);
            $this->inserted++;

            return 'inserted';
        }

        if ($find->time === $record['time']) {
            $this->duplicates++;

            return 'duplicate';
        }

        $historic = new RecordHistory();
        $historic->fill($find->toArray());
        $historic->save();

        $find->delete();

        $this->insertRecord($record);
        $this->updated++;

        return 'updated';
    }

    private function insertRecord(array $record): void
    {
        $newrecord = new Record();

        $record['map'] = strtolower($record['map']);

        $newrecord->name = $record['name'];
        $newrecord->mapname = $record['map'];
        $newrecord->mdd_id = $record['mdd_id'];
        $newrecord->date_set = $record['date'];
        $newrecord->physics = $record['physics'];
        $newrecord->mode = $record['mode'];
        $newrecord->country = $record['country'];
        $newrecord->time = $record['time'];
        $newrecord->gametype = $record['mode'] . '_' . $record['physics'];

        $user = User::where('mdd_id', $record['mdd_id'])->first();

        if ($user) {
            $newrecord->user_id = $user->id;
        }

        $newrecord->save();

        if (! $this->live) {
            return;
        }

        $serverMap = Map::where('name', $record['map'])->first();

        if ($serverMap) {
            $serverMap->processRanks();
            $serverMap->processAverageTime();

            // Refresh rank from DB and update the cached records page
            $newrecord->refresh();
            RecordsController::prependToCache($newrecord);

            // New MDD record -> refresh that map's materialized Demos Top
            // ranking (queue eligibility). Coalesced per map.
            RebuildDemosTopRanksJob::dispatch($serverMap->name);
        }

        $mdd_profile = MddProfile::where('id', $newrecord->mdd_id)->first();

        if ($mdd_profile) {
            $mdd_profile->processStats();
        } else {
            ScrapeProfile::dispatch($newrecord->mdd_id);
        }

        ProcessNotificationsJob::dispatch($newrecord);

        // Incrementally recalculate ratings for this map
        RecalcMapRatingsJob::dispatch($newrecord->mapname, $newrecord->physics, $newrecord->mode);
    }
}
