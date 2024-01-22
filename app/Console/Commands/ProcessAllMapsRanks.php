<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Models\Map;

class ProcessAllMapsRanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-all-maps-ranks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maps = Map::all();

        foreach($maps as $map) {
            $map->processRanks();

            $this->info("(" . $map->name . ") Proccessed Successfully.");
        }
    }
}
