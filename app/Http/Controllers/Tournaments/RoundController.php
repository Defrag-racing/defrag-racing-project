<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;

use App\Models\TournamentListing;

use Carbon\Carbon;

class RoundController extends Controller {
    public function index (Tournament $tournament) {
        $listings = TournamentListing::where('tournament_id', $tournament->id)
                ->orderBy('order', 'DESC')
                ->with('round.maps')
                ->with('comments.user')
                ->get();

        return Inertia::render('Tournaments/Tournament/Rounds')
                ->with('tournament', $tournament)
                ->with('listings', $listings);
    }

    public function comment(Tournament $tournament, TournamentListing $listing, Request $request) {
        $listing->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment
        ]);

        return redirect()->route('tournaments.rounds.index', $tournament->id);
    }
}
