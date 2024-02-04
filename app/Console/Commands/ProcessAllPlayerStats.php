<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\MddProfile;

class ProcessAllPlayerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-all-player-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all player stats';

    /**
     * Execute the console command.
     */
    public function handle() {
        $profiles = MddProfile::all();

        foreach ($profiles as $profile) {
            $profile->processStats();
        }
    }
}
