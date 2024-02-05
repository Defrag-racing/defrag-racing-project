<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Jobs\ScrapeRecords as ScrapeRecordsJob;

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
        $start = $this->argument('start');
        $pages = $this->argument('pages');

        dispatch(new ScrapeRecordsJob());
    }
}
