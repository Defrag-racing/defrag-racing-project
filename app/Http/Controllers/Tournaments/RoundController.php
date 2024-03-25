<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\TournamentListing;

use Carbon\Carbon;

class RoundController extends Controller {
    public function index (Tournament $tournament) {
        $listings = TournamentListing::where('tournament_id', $tournament->id)
                ->with('round')
                ->orderBy('order', 'DESC')
                ->get();

        return Inertia::render('Tournaments/Tournament/Rounds')
                ->with('tournament', $tournament)
                ->with('listings', $listings);
    }
}
