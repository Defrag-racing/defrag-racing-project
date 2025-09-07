<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Jobs\GetLastMddRecords;
use App\Jobs\TournamentCalculationsJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
        // mdd scrapes...
        $schedule->command('scrape:servers')->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();
        $schedule->job(new GetLastMddRecords)->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();
        $schedule->command('scrape:maps')->withoutOverlapping()->evenInMaintenanceMode()->everyTwoMinutes();

        $schedule->job(new TournamentCalculationsJob)->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();

        $schedule->command('tournaments:notifications-send')->everyTwoMinutes();

        $schedule->command('run:calculate-ratings')->withoutOverlapping()->everySixHours(); // ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
