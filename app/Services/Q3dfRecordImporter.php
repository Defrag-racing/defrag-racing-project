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
use Illuminate\Support\Facades\DB;
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

    /** Why the last record failed, or null when it did not. */
    public ?string $lastError = null;

    public function __construct(private bool $live = true)
    {
    }

    /**
     * One bad record is logged and skipped, so it cannot stop the rest.
     */
    public function import(array $records): void
    {
        foreach ($records as $record) {
            $this->lastError = null;

            try {
                $this->importOne($record);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->lastError = $e->getMessage();
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
     * @return string inserted, updated, restored or duplicate
     */
    public function importOne(array $record): string
    {
        $find = Record::query()
            ->where('physics', $record['physics'])
            ->where('mode', $record['mode'])
            ->where('mdd_id', $record['mdd_id'])
            ->where('mapname', $record['map'])
            ->first();

        if ($find && $find->time === $record['time']) {
            $this->duplicates++;

            return 'duplicate';
        }

        // We may already hold this exact time as a deleted row. The unique
        // index counts deleted rows too, so a new row with that time cannot be
        // written. It came from the old import: it deleted the live row, then
        // failed to write the new time for this reason, and the player was
        // left with no record at all. That deleted row is the record q3df
        // lists, so it comes back.
        $deleted = Record::onlyTrashed()
            ->where('physics', $record['physics'])
            ->where('mode', $record['mode'])
            ->where('mdd_id', $record['mdd_id'])
            ->where('mapname', strtolower($record['map']))
            ->where('time', $record['time'])
            ->first();

        if ($deleted) {
            DB::transaction(function () use ($find, $deleted) {
                if ($find) {
                    $historic = new RecordHistory();
                    $historic->fill($find->toArray());
                    $historic->save();

                    $find->delete();
                }

                // The row went into record_histories when it was replaced.
                // It is the current time again, so it is not history now.
                RecordHistory::where('mdd_id', $deleted->mdd_id)
                    ->where('mapname', $deleted->mapname)
                    ->where('physics', $deleted->physics)
                    ->where('mode', $deleted->mode)
                    ->where('time', $deleted->time)
                    ->delete();

                $deleted->restore();
            });

            $this->afterInsert($deleted);
            $find ? $this->updated++ : $this->inserted++;

            return 'restored';
        }

        if (! $find) {
            $this->afterInsert($this->writeRecord($record));
            $this->inserted++;

            return 'inserted';
        }

        // One transaction: when the new row cannot be written, the old one must
        // stay. Without it a failed insert left the player with no record at all.
        $newrecord = DB::transaction(function () use ($find, $record) {
            $historic = new RecordHistory();
            $historic->fill($find->toArray());
            $historic->save();

            $find->delete();

            return $this->writeRecord($record);
        });

        $this->afterInsert($newrecord);
        $this->updated++;

        return 'updated';
    }

    private function writeRecord(array $record): Record
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

        return $newrecord;
    }

    private function afterInsert(Record $newrecord): void
    {
        if (! $this->live) {
            return;
        }

        $serverMap = Map::where('name', $newrecord->mapname)->first();

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
