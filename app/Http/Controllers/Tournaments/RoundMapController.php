<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\Round;
use App\Models\RoundMap;

use Carbon\Carbon;

class RoundMapController extends Controller {
    public function index (Tournament $tournament, Round $round) {
        $round->load('maps');

        return Inertia::render('Tournaments/Management/Rounds/Maps')
                ->with('tournament', $tournament)
                ->with('round', $round);
    }

    public function create(Tournament $tournament, Round $round) {
        return Inertia::render('Tournaments/Management/Rounds/Create')
                ->with('tournament', $tournament)
                ->with('round', $round);
    }

    public function store(Request $request, Tournament $tournament, Round $round) {
        $request->validate([
            'name'          =>      'required|string|max:255',
            'pk3'           =>      'required'
        ]);

        $round = $round->maps()->create([
            'name'          =>      $request->name,
            'pk3'           =>      $request->file('pk3')->store('tournaments/rounds/maps'),
            'crc'           =>      $request->crc ?? ''
        ]);

        return redirect()->back()->withSuccess('Map created successfully !');
    }

    public function destroy(Tournament $tournament, Round $round, RoundMap $map) {
        $map->delete();

        return redirect()->back()->withSuccess('Map deleted successfully !');
    }
}
