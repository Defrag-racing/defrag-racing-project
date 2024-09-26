<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CalculateRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:calculate-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Python script to calculate players ratings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $activate = 'source ' . base_path('storage/ranking_calculator/bin/activate');
        $script = 'python3 ' . base_path('storage/ranking_calculator/main.py');
        $env = '--env_path ' . base_path('.env');

        // $command = $activate . ' && ' . $script . ' ' . $env;
        $command = '/bin/bash -c "' . $activate . ' && ' . $script . ' ' . $env . '"';

        file_put_contents(base_path('my_simple_log.txt'), $command . PHP_EOL, FILE_APPEND);

        exec($command);

        return 0;
    }
}
