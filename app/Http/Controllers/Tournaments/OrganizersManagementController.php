<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;
use App\Models\User;

use Carbon\Carbon;

class OrganizersManagementController extends Controller {
    public function index (Tournament $tournament) {
        $organizers = Organizer::where('tournament_id', $tournament->id)
            ->with('user')
            ->get();

        return Inertia::render('Tournaments/Management/Organizers/Index')
                ->with('tournament', $tournament)
                ->with('organizers', $organizers);
    }

    public function create(Tournament $tournament) {
        $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);

        return Inertia::render('Tournaments/Management/Organizers/Create')
                ->with('tournament', $tournament)
                ->with('users', $users);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|string|max:255'
        ]);

        $roles = ['organizer', 'mapper', 'tester', 'validator'];

        if (!in_array($request->role, $roles)) {
            return redirect()->back()->withDanger('Invalid Role');
        }

        $organizer = new Organizer();
        $organizer->user_id = $request->user_id;
        $organizer->role = $request->role;
        $organizer->tournament_id = $tournament->id;
        $organizer->save();

        return redirect()->route('tournaments.organizers.manage', $tournament);
    }

    public function edit(Tournament $tournament, Organizer $organizer) {
        $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);

        return Inertia::render('Tournaments/Management/Organizers/Edit')
                ->with('tournament', $tournament)
                ->with('organizer', $organizer)
                ->with('users', $users);
    }

    public function update(Request $request, Tournament $tournament, Organizer $organizer) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|string|max:255'
        ]);

        $organizer->user_id = $request->user_id;
        $organizer->role = $request->role;

        $organizer->save();

        return redirect()->route('tournaments.organizers.manage', $tournament);
    }

    public function destroy(Tournament $tournament, Organizer $organizer) {
        if ($organizer->role === 'admin') {
            return redirect()->back()->withDanger('Cannot delete admin');
        }

        $organizer->delete();

        return redirect()->route('tournaments.organizers.manage', $tournament);
    }
}
