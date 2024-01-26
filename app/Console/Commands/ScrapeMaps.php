<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\External\WorldSpawn;

class ScrapeMaps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:maps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes all the latest maps';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ws = new WorldSpawn();

        $ws->getMapDetails('vp-omnimine-s3-ultra');
    }
}
