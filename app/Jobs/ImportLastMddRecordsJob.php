<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\External\Q3DFLastRecordsApi;
use App\Models\User;
use App\Models\Map;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\MddProfile;

use App\Jobs\ProcessNotificationsJob;
use App\Jobs\RecalcMapRatingsJob;
use App\Http\Controllers\RecordsController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ImportLastMddRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    private int $duplicateCount = 0;
    private int $insertedCount = 0;
    private int $updatedCount = 0;
    private int $failedCount = 0;

    public function __construct() {}

    public function handle(): void{
        $api = new Q3DFLastRecordsApi();
        $key = config('services.q3df.key');
        $perPage = $key ? 1000 : 100;
        $page = 0;
        $fetchedCount = 0;
        $reachedExisting = false;

        $this->duplicateCount = 0;
        $this->insertedCount = 0;
        $this->updatedCount = 0;
        $this->failedCount = 0;

        while (true) {
            $fetched = $api->getMddRecords($page, $perPage, $key ?: null);

            if ($fetched === null) {
                echo ("Fetching page $page failed.") . PHP_EOL;
                Log::warning('ImportLastMddRecordsJob: page fetch failed', ['page' => $page]);
                break;
            }

            list(, , $records) = $fetched;
            $pageCount = count($records);
            $fetchedCount += $pageCount;

            if ($pageCount === 0) {
                $reachedExisting = true;
                break;
            }

            $insertedBefore = $this->insertedCount;
            $updatedBefore = $this->updatedCount;

            $this->logApiRecords($records);
            $this->processRecords($records);

            $newOnPage = ($this->insertedCount - $insertedBefore) + ($this->updatedCount - $updatedBefore);
            echo ("Page $page: $pageCount records, $newOnPage new or updated") . PHP_EOL;

            if ($newOnPage === 0 || $pageCount < $perPage) {
                $reachedExisting = true;
                break;
            }

            $page++;
        }

        if ($fetchedCount === 0) {
            echo ('No records found !') . PHP_EOL;
            return;
        }

        echo ("Finished. Total: {$fetchedCount}, New: {$this->insertedCount}, Updated: {$this->updatedCount}, Duplicates: {$this->duplicateCount}, Skipped: {$this->failedCount}") . PHP_EOL;

        // A full page of only new records, and no later page reached rows we already have.
        if (!$reachedExisting && $fetchedCount >= 20 && $this->duplicateCount === 0) {
            $this->sendGapAlert($fetchedCount);
        }
    }

    private function sendGapAlert(int $batchSize): void
    {
        // Find the last record before this batch (by created_at)
        $lastRecord = Record::orderBy('created_at', 'desc')
            ->skip($this->insertedCount)
            ->first();

        $lastRecordInfo = $lastRecord
            ? "{$lastRecord->mapname} by {$lastRecord->name} ({$lastRecord->physics}) at {$lastRecord->date_set}"
            : 'Unknown';

        $message = "RECORD SCRAPER GAP DETECTED\n\n"
            . "Batch of {$batchSize} records had 0 duplicates - this means records were likely missed between scraper runs.\n\n"
            . "New records inserted: {$this->insertedCount}\n"
            . "Updated records: {$this->updatedCount}\n"
            . "Last known record before gap: {$lastRecordInfo}\n\n"
            . "Action needed: Check Q3DF_API_KEY and run php artisan scrape:records again.\n"
            . "Time: " . now()->toDateTimeString();

        Log::warning('Record scraper gap detected', [
            'batch_size' => $batchSize,
            'duplicates' => 0,
            'inserted' => $this->insertedCount,
            'last_record' => $lastRecordInfo,
        ]);

        // Email all admins
        try {
            $adminEmails = User::where('admin', true)->pluck('email')->toArray();

            if (!empty($adminEmails)) {
                Mail::raw($message, function ($mail) use ($adminEmails) {
                    $mail->to($adminEmails)
                        ->subject('[Defrag Racing] Record Scraper Gap Detected - Records May Be Missing');
                });
                echo "Gap alert email sent to: " . implode(', ', $adminEmails) . PHP_EOL;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send gap alert email: ' . $e->getMessage());
            echo "Failed to send gap alert email: " . $e->getMessage() . PHP_EOL;
        }
    }

    private function logApiRecords(array $records): void
    {
        foreach ($records as $record) {
            try {
                DB::table('api_records_log')->updateOrInsert(
                    [
                        'mdd_id' => $record['mdd_id'],
                        'mapname' => strtolower($record['map']),
                        'physics' => $record['physics'],
                        'mode' => $record['mode'],
                    ],
                    [
                        'time' => $record['time'],
                        'name' => $record['name'],
                        'date_set' => $record['date'],
                    ]
                );
            } catch (\Exception $e) {
                // Don't let logging break the main job
                Log::warning('Failed to log API record: ' . $e->getMessage());
            }
        }
    }

    private function processRecords($records) {
        foreach($records as $record) {
            // Isolate each record: a single bad row (e.g. a duplicate-key
            // collision from a pre-existing orphaned record) used to throw and
            // abort the ENTIRE minute's batch - every record after it was
            // skipped and its RecalcMapRatingsJob never dispatched, which is
            // what froze the rankings. Now one bad record is logged and
            // skipped; the rest of the batch (and their rating recalcs) run.
            try {
                $this->processOneRecord($record);
            } catch (\Throwable $e) {
                $this->failedCount++;
                Log::warning('ImportLastMddRecordsJob: skipped a record after an error', [
                    'mdd_id'  => $record['mdd_id'] ?? null,
                    'map'     => $record['map'] ?? null,
                    'physics' => $record['physics'] ?? null,
                    'mode'    => $record['mode'] ?? null,
                    'time'    => $record['time'] ?? null,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function processOneRecord($record) {
        $find = Record::query()
                ->where('physics', $record['physics'])
                ->where('mode', $record['mode'])
                ->where('mdd_id', $record['mdd_id'])
                ->where('mapname', $record['map'])->first();

        if (! $find) {
            $this->insertRecord($record);
            $this->insertedCount++;
            return;
        }

        if ($find->time === $record['time']) {
            $this->duplicateCount++;
            echo ("Duplicate Found [" . $find->name . "] (" . $find->time . ") (" . $find->mapname . ") (" . $find->physics . ")") . PHP_EOL;
            return;
        }

        if ($find->time !== $record['time']) {
            $this->insertHistoricRecord($find, $record);
            $this->updatedCount++;
        }

        $this->insertRecord($record);
        $this->insertedCount++;
    }

    private function insertHistoricRecord($oldrecord, $newrecord) {
        $historic = new RecordHistory();
        $historic->fill($oldrecord->toArray());

        $historic->save();

        $oldrecord->delete();
    }

    private function insertRecord($record) {
        echo ("Inserting Record [" . $record['name'] . "] (" . $record['time'] . ") (" . $record['map'] . ") (" . $record['physics'] . ") (" . $record['mdd_id'] . ")") . PHP_EOL;

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

        $serverMap = Map::where('name', $record['map'])->first();

        if ($serverMap) {
            $serverMap->processRanks();
            $serverMap->processAverageTime();

            // Refresh rank from DB and update the cached records page
            $newrecord->refresh();
            RecordsController::prependToCache($newrecord);

            // New MDD record -> refresh that map's materialized Demos Top
            // ranking (queue eligibility). Coalesced per map.
            \App\Jobs\RebuildDemosTopRanksJob::dispatch($serverMap->name);
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

    private function get_int_parameter($name) {
        $param = $this->$name;

        return intval($param);
    }
}
