<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Tournament;

class TournamentNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
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
