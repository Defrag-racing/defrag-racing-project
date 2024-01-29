<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Models\Map;

class ProcessAllMapsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-all-maps-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all maps data (ranks, average time)';

    /**
     * Execute the console command.
     */
    public function handle() {
        $maps = Map::all();

        foreach($maps as $map) {
            $map->processRanks();
            $map->processAverageTime();

            $this->info("(" . $map->name . ") Proccessed Successfully.");
        }
    }
}
