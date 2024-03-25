<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\External\Q3DFRecords;
use App\Models\User;
use App\Models\Map;
use App\Models\Record;
use App\Models\RecordHistory;
use App\Models\MddProfile;

use App\Jobs\ProcessNotificationsJob;

class ScrapeRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $start;
    private $pages;

    public function __construct($start = 1, $pages = 0) {
        $this->start = $start;
        $this->pages = $pages;
    }

    public function handle(): void{
        $start = $this->get_int_parameter('start');
        $pages = $this->get_int_parameter('pages');

        $scraper = new Q3DFRecords();

        $records = [];

        if ($pages == 0) {
            $lastRecord = Record::orderBy('date_set', 'DESC')->first();
            $records = $scraper->scrape_until($start, $lastRecord);
        } else {
            $records = $scraper->scrape_through($start, $pages);
        }

        if (count($records) === 0) {
            echo ('No records found !') . PHP_EOL;
        }

        echo 'WE ARE FINISHED SCRAPPING' . PHP_EOL;

        $this->processRecords($records);

        echo ("Finished Running the scrapper.") . PHP_EOL;
    }

    private function processRecords($records) {
        foreach($records as $record) {
            $find = Record::query()
                    ->where('physics', $record['physics'])
                    ->where('mode', $record['mode'])
                    ->where('mdd_id', $record['mdd_id'])
                    ->where('mapname', $record['map'])->first();
            
            echo ("Checking Record [" . $record['name'] . "] (" . $record['time'] . ") (" . $record['map'] . ") (" . $record['physics'] . ")") . PHP_EOL;

            if (! $find) {
                echo ("Record Not Found [" . $record['name'] . "] (" . $record['time'] . ") (" . $record['map'] . ") (" . $record['physics'] . ")") . PHP_EOL;
                $this->insertRecord($record);
                continue;
            }

            if ($find->time === $record['time']) {
                echo ("Duplicate Found [" . $find->name . "] (" . $find->time . ") (" . $find->mapname . ") (" . $find->physics . ")") . PHP_EOL;
                continue;
            }

            if ($find->time !== $record['time']) {
                echo 'Historic Record Found [' . $find->name . '] (' . $find->time . ') (' . $find->mapname . ') (' . $find->physics . ')' . PHP_EOL;

                $this->insertHistoricRecord($find, $record);
            }

            echo 'Adding Record [' . $record['name'] . '] (' . $record['time'] . ') (' . $record['map'] . ') (' . $record['physics'] . ')' . PHP_EOL;

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
        }

        $mdd_profile = MddProfile::where('id', $newrecord->mdd_id)->first();

        if ($mdd_profile) {
            $mdd_profile->processStats();
        } else {
            ScrapeProfile::dispatch($newrecord->mdd_id);
        }

        ProcessNotificationsJob::dispatch($newrecord);
    }

    private function get_int_parameter($name) {
        $param = $this->$name;

        return intval($param);
    }
}
