<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\TournamentSuggestion;

use Carbon\Carbon;

class SuggestionController extends Controller {
    public function index (Tournament $tournament) {
        $tournament->load('suggestions');

        return Inertia::render('Tournaments/Management/Suggestions/Index')
                ->with('tournament', $tournament);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'title'  =>  'required|string|max:255',
            'message' => 'required|string'
        ]);

        $suggestion = $tournament->suggestions()->create([
            'title' => $request->title,
            'message' => $request->message,
            'user_id' => $request->user()->id
        ]);

        return redirect()->route('tournaments.show', $tournament);
    }

    public function destroy(Tournament $tournament, TournamentSuggestion $suggestion) {
        $suggestion->delete();

        return redirect()->route('tournaments.suggestions.index', $tournament);
    }
}
