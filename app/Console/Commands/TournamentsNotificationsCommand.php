<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Tournament;
use App\Models\User;

class TournamentsNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:notifications-send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications that are related to tournaments';

    /**
     * Execute the console command.
     */
    public function handle(){
        $tournaments = Tournament::where('published', true)->get();

        foreach($tournaments as $tournament) {
            $firstRound = $tournament->rounds()->where('published', true)->orderBy('start_date', 'asc')->first(); 
            $users = User::all();

            if (! $firstRound) {
                continue;
            }

            foreach($users as $user) {
                $user->tournamentNotify('tournament_start', 'The tournament ', $tournament->name, ' is starting soon.', '/tournaments/' . $tournament->id);
            }
        }
    }
}
