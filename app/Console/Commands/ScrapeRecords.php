<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\External\Q3DFRecords;
use App\Models\User;
use App\Models\Record;
use App\Models\RecordHistory;

use App\Jobs\ProcessNotificationsJob;

class ScrapeRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:records {start=1} {pages=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape records data';

    /**
     * Execute the console command.
     */
    public function handle() {
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
            $this->error('No records found !');
        }

        $this->processRecords($records);

        $this->info("Finished Running the scrapper.");
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
                $this->info("Duplicate Found [" . $find->name . "] (" . $find->time . ") (" . $find->mapname . ") (" . $find->physics . ")");
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
        $this->info("Inserting Record [" . $record['name'] . "] (" . $record['time'] . ") (" . $record['map'] . ") (" . $record['physics'] . ") (" . $record['mdd_id'] . ")");

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
        }

        ProcessNotificationsJob::dispatch($newrecord);
    }

    private function get_int_parameter($name) {
        $param = $this->argument($name);

        return intval($param);
    }
}
