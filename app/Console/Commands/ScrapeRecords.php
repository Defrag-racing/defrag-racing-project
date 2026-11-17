<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Jobs\ImportLastMddRecordsJob;

class ScrapeRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import recent q3df records from the API until they overlap the database';

    /**
     * Execute the console command.
     */
    public function handle() {
        dispatch(new ImportLastMddRecordsJob());
    }
}
