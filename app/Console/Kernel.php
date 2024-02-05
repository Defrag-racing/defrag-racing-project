<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Jobs\ScrapeRecords;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
        $schedule->command('scrape:servers')->everyMinute();

        $schedule->command('scrape:servers 1')->everyFiveMinutes()->runInBackground();

        $schedule->job(new ScrapeRecords)->withoutOverlapping()->evenInMaintenanceMode()->everyThirtySeconds();

        $schedule->command('scrape:maps')->everyTwoMinutes();
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
