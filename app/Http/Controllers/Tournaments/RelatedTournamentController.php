<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;
use App\Models\User;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\RelatedTournament;

use Carbon\Carbon;

class RelatedTournamentController extends Controller {
    public function index (Tournament $tournament) {
        $tournament->load('relatedTournaments.tournament');

        return Inertia::render('Tournaments/Management/RelatedTournaments/Index')
                ->with('tournament', $tournament);
    }

    public function create(Tournament $tournament) {
        $tournaments = Tournament::query()
            ->where('published', true)
            ->get();


        return Inertia::render('Tournaments/Management/RelatedTournaments/Create')
                ->with('tournament', $tournament)
                ->with('tournaments', $tournaments);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'related_tournament_id' => 'required|exists:tournaments,id',
        ]);

        $streamer = $tournament->relatedTournaments()->create([
            'related_tournament_id' => $request->related_tournament_id,
        ]);

        return redirect()->route('tournaments.related.manage', $tournament);
    }

    public function edit(Tournament $tournament, RelatedTournament $relatedTournament) {
        $tournaments = Tournament::query()
            ->where('published', true)
            ->get();

        return Inertia::render('Tournaments/Management/RelatedTournaments/Edit')
                ->with('tournament', $tournament)
                ->with('relatedTournament', $relatedTournament)
                ->with('tournaments', $tournaments);
    }

    public function update(Request $request, Tournament $tournament, RelatedTournament $relatedTournament) {
        $request->validate([
            'related_tournament_id' => 'required|exists:tournaments,id',
        ]);

        $relatedTournament->update([
            'related_tournament_id' => $request->related_tournament_id,
        ]);

        return redirect()->route('tournaments.related.manage', $tournament);
    }

    public function destroy(Tournament $tournament, RelatedTournament $relatedTournament) {
        $relatedTournament->delete();

        return redirect()->route('tournaments.related.manage', $tournament);
    }
}
