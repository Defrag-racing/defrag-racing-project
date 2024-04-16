<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Demo;
use App\Models\Round;
use App\External\DemoValidator;

class FixDemosValidationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix-demos-validation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix the validation of demos.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $demos = Demo::all();

        foreach($demos as $demo) {
            $this->info('Validating demo ' . $demo->filename . '...');
            $demoValidator = new DemoValidator($demo->file, $demo->filename);

            $demoValidator->validate();

            $demoValidator->validate_maps($demo->round);

            $demo_data = $demoValidator->get_data();

            $demo->time = $demo_data['time'];

            $demo->save();
        }

        $rounds = Round::where('results', true)->get();

        foreach($rounds as $round) {
            $round->calculateResults();
        }

        $this->info('Demos validation fixed.');
    }
}
