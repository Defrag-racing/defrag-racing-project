<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\TournamentCalculationsJob;

class TournamentCalculations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournament:calculate {force=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates results of tournaments and rounds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->argument('force');

        TournamentCalculationsJob::dispatch($force === "true");
    }
}
