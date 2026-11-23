<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\External\Q3DFLastRecordsApi;
use App\Services\Q3dfRecordImporter;
use App\Models\User;
use App\Models\Record;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * ShouldBeUnique keeps one copy at a time. The scheduler queues this job every
 * minute, and withoutOverlapping() on a scheduled job only guards the dispatch,
 * not the run. A catch-up that takes longer than a minute was run by several
 * workers at once, all walking the same pages.
 */
class ImportLastMddRecordsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    // Longer than $timeout, so a killed run cannot keep the lock forever.
    public $uniqueFor = 1300;

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

        $importer = new Q3dfRecordImporter(live: true);

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

            $changedBefore = $importer->inserted + $importer->updated;

            $this->logApiRecords($records);
            $importer->import($records);

            $newOnPage = $importer->inserted + $importer->updated - $changedBefore;
            echo ("Page $page: $pageCount records, $newOnPage new or updated") . PHP_EOL;

            if ($newOnPage === 0 || $pageCount < $perPage) {
                $reachedExisting = true;
                break;
            }

            $page++;
        }

        $this->insertedCount = $importer->inserted;
        $this->updatedCount = $importer->updated;
        $this->duplicateCount = $importer->duplicates;
        $this->failedCount = $importer->failed;

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
}
