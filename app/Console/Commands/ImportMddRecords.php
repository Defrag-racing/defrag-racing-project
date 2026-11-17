<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\External\Q3DFRecordsApi;
use App\Models\User;
use App\Models\Map;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\MddProfile;
use App\Jobs\ScrapeProfile;
use App\Jobs\ProcessNotificationsJob;

class ImportMddRecords extends Command
{
    /**
     * mdd ids seen on this page that have no profile yet. Dispatched after the page commits.
     *
     * @var array<int, true>
     */
    private $profilesToScrape = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:mdd:records {api_key} {--page=0} {--per_page=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'import records from mddd api';

    /**
     * Execute the console command.
     */
    public function handle() {
        $api = new Q3DFRecordsApi();
        echo ("Starting the ImportMddRecords from $api->url") . PHP_EOL;

        $per_page = (int) $this->option('per_page');
        $page = (int) $this->option('page');
        echo ("Using page $page and per_page $per_page") . PHP_EOL;

        while (true) {
            $start = microtime(true);
            $fetched = $api->getMddRecords($page, $per_page, $this->argument('api_key'));

            if ($fetched === null) {
                echo ("Fetching page $page failed. Already imported pages are kept. Resume with --page=$page") . PHP_EOL;
                return 1;
            }

            list($ttfb, $count, $records) = $fetched;

            DB::transaction(function () use ($records) {
                $this->processRecords($records);
            });
            $this->dispatchProfileScrapes();

            $duration = microtime(true) - $start;
            echo ("Fetching page $page -> (".(($per_page*$page)+count($records))."/" . ($count) . ") -> TTFB (Q3df-API)=$ttfb ms -> Overall duration=$duration ms") . PHP_EOL;

            if (count($records) === 0) {
                break;
            }

            $page += 1;
        }

        $this->updateMaps();
        $this->updateProfiles();

        echo ("Finished Running the Import MDD Records.") . PHP_EOL;
        return 0;
    }

    private function dispatchProfileScrapes() {
        foreach (array_keys($this->profilesToScrape) as $mddId) {
            ScrapeProfile::dispatch($mddId);
        }

        $this->profilesToScrape = [];
    }

    private function processRecords($records) {
        foreach($records as $record) {
            $find = Record::query()
                    ->where('physics', $record['physics'])
                    ->where('mode', $record['mode'])
                    ->where('mdd_id', $record['mdd_id'])
                    ->where('mapname', $record['map'])->first();

            if (! $find) {
                $this->insertRecord($record);
                continue;
            }

            if ($find->time === $record['time']) {
                //echo ("Duplicate Found [" . $find->name . "] (" . $find->time . ") (" . $find->mapname . ") (" . $find->physics . ")") . PHP_EOL;
                continue;
            }

            if ($find->time !== $record['time']) {
                $this->insertHistoricRecord($find, $record);
            }

            $this->insertRecord($record);
        }
    }

    private function insertHistoricRecord($oldrecord, $newrecord) {
        $historic = new RecordHistory();
        $historic->fill($oldrecord->toArray());

        $historic->save();

        $oldrecord->delete();
    }

    private function insertRecord($record) {
        //echo ("Inserting Record [" . $record['name'] . "] (" . $record['time'] . ") (" . $record['map'] . ") (" . $record['physics'] . ") (" . $record['mdd_id'] . ")") . PHP_EOL;

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

        if (!MddProfile::where('id', $newrecord->mdd_id)->exists()) {
            $this->profilesToScrape[$newrecord->mdd_id] = true;
        }
    }

    private function get_int_parameter($name) {
        $param = $this->$name;

        return intval($param);
    }

    private function updateMaps() {
                echo ("Processing maps:") . PHP_EOL;
        foreach (Map::all() as $map) {
            $start = microtime(true);
            $map->processRanks();
            $map->processAverageTime();

            $end = microtime(true);
            $duration = $end - $start;
            echo (" -> Processed map {$map->name} in $duration seconds") . PHP_EOL;
        }
        echo PHP_EOL;
    }

    private function updateProfiles() {
        echo ("Processing users:") . PHP_EOL;
        foreach (MddProfile::all() as $user) {
            $start = microtime(true);
            $user->processStats();

            $end = microtime(true);
            $duration = $end - $start;
            echo (" -> Processed user {$user->name} in $duration seconds") . PHP_EOL;
        }
        echo PHP_EOL;
    }
}
