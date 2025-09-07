<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Jobs\GetLastMddRecords as GetLastMddRecordsJob;

class GetLastMddRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:last:mdd:records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get last records from mddd api';

    /**
     * Execute the console command.
     */
    public function handle() {
        dispatch(new GetLastMddRecordsJob());
    }
}
