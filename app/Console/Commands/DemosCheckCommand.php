<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Round;
use App\Models\User;

class DemosCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demos-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check demos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rounds = Round::all();

        foreach ($rounds as $round) {
            $users = User::whereHas('demos', function ($query) use ($round) {
                $query->where('round_id', $round->id)
                    ->where('rejected', false);
            })->get();

            foreach ($users as $user) {
                $user->check_demos($round->id);
            }

            $round->calculateResults();
        }
    }
}
