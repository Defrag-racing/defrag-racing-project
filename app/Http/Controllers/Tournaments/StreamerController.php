<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;
use App\Models\User;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\TournamentStreamer;

use Carbon\Carbon;

class StreamerController extends Controller {
    public function index (Tournament $tournament) {
        $tournament->load('streamers.user:id,name,profile_photo_path,country,twitch_name');

        return Inertia::render('Tournaments/Management/Streamers/Index')
                ->with('tournament', $tournament);
    }

    public function create(Tournament $tournament) {
        $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);

        return Inertia::render('Tournaments/Management/Streamers/Create')
                ->with('tournament', $tournament)
                ->with('users', $users);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'twitch_username' => 'required|string|max:255',
        ]);

        $streamer = $tournament->streamers()->create([
            'user_id' => $request->user_id,
            'twitch_username' => $request->twitch_username,
        ]);

        return redirect()->route('tournaments.streamers.manage', $tournament);
    }

    public function edit(Tournament $tournament, TournamentStreamer $streamer) {
        $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);

        return Inertia::render('Tournaments/Management/Streamers/Edit')
                ->with('tournament', $tournament)
                ->with('streamer', $streamer)
                ->with('users', $users);
    }

    public function update(Request $request, Tournament $tournament, TournamentStreamer $streamer) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'twitch_username' => 'required|string|max:255',
        ]);

        $streamer->update([
            'user_id' => $request->user_id,
            'twitch_username' => $request->twitch_username,
        ]);

        return redirect()->route('tournaments.streamers.manage', $tournament);
    }

    public function destroy(Tournament $tournament, TournamentStreamer $streamer) {
        $streamer->delete();

        return redirect()->route('tournaments.streamers.manage', $tournament);
    }
}
