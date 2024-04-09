<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;

use Carbon\Carbon;

use Illuminate\Validation\Rule;

class TeamController extends Controller {
    public function index(Request $request, Tournament $tournament) {
        $teams = Team::where('tournament_id', $tournament->id)
            ->whereNotNull('cpm_player_id')
            ->whereNotNull('vq3_player_id')
            ->with('cpmPlayer:id,name,country,profile_photo_path', 'vq3Player:id,name,country,profile_photo_path')
            ->get();

        if ($request->user()) {
            $myTeam = Team::where('tournament_id', $tournament->id)
                ->where('cpm_player_id', $request->user()->id)
                ->orWhere('vq3_player_id', $request->user()->id)
                ->with('cpmPlayer:id,name,country,profile_photo_path', 'vq3Player:id,name,country,profile_photo_path')
                ->first();
        } else {
            $myTeam = null;
        }

        return Inertia::render('Tournaments/Teams/Index')
            ->with('tournament', $tournament)
            ->with('teams', $teams)
            ->with('myTeam', $myTeam);
    }

    public function manage(Tournament $tournament, Request $request) {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $users = User::orderBy('plain_name', 'ASC')->get([
            'id',
            'name',
            'plain_name',
            'country'
        ]);

        $team = Team::where('tournament_id', $tournament->id)
            ->where('cpm_player_id', $request->user()->id)
            ->orWhere('vq3_player_id', $request->user()->id)
            ->with('cpmPlayer:id,name,country,profile_photo_path', 'vq3Player:id,name,country,profile_photo_path')
            ->first();

        $invitations = TeamInvite::where('tournament_id', $tournament->id)
            ->where('user_id', $request->user()->id)
            ->where('accepted', false)
            ->with('team.cpmPlayer:id,name,country,profile_photo_path', 'team.vq3Player:id,name,country,profile_photo_path')
            ->orderBy('created_at', 'DESC')
            ->get();

        return Inertia::render('Tournaments/Teams/ManageTeams')
            ->with('tournament', $tournament)
            ->with('team', $team)
            ->with('invitations', $invitations)
            ->with('users', $users);
    }

    public function create(Tournament $tournament) {
        return Inertia::render('Tournaments/Teams/Create')
                ->with('tournament', $tournament);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'name'  =>  'required|string|max:255',
            'type'  =>  Rule::in(['cpm', 'vq3']),
        ]);

        $team = new Team();
        $team->name = $request->name;
        $team->tournament_id = $tournament->id;

        if ($request->type == 'cpm') {
            $team->cpm_player_id = $request->user()->id;
        } else {
            $team->vq3_player_id = $request->user()->id;
        }

        $team->save();

        return redirect()->route('tournaments.teams.manage', $tournament);
    }

    public function leave(Request $request, Tournament $tournament) {
        $team = Team::where('tournament_id', $tournament->id)
            ->where('cpm_player_id', $request->user()->id)
            ->orWhere('vq3_player_id', $request->user()->id)
            ->first();

        if (! $team) {
            return redirect()->back()->withDanger('You are not a part of a team !');
        }

        if ($team->cpm_player_id == $request->user()->id) {
            $team->cpm_player_id = null;
        } else {
            $team->vq3_player_id = null;
        }

        if ($team->cpm_player_id == null && $team->vq3_player_id == null) {
            $team->invitations()->delete();
            $team->delete();
        } else {
            $team->save();
        }
        
        return redirect()
            ->route('tournaments.teams.manage', $tournament)
            ->withSuccess('You have left the team successfully');
    }

    public function invite(Request $request, Tournament $tournament) {
        $request->validate([
            'player_id' => 'required|exists:users,id'
        ]);

        $team = Team::where('tournament_id', $tournament->id)
            ->where('cpm_player_id', $request->user()->id)
            ->orWhere('vq3_player_id', $request->user()->id)
            ->first();

        if (! $team) {
            return redirect()->back()->withDanger('You are not a part of a team !');
        }

        if ($team->cpm_player_id && $team->vq3_player_id) {
            return redirect()->back()->withDanger('Your team is already full !');
        }

        $otherTeam = Team::where('tournament_id', $tournament->id)
            ->where('cpm_player_id', $request->player_id)
            ->orWhere('vq3_player_id', $request->player_id)
            ->first();

        if ($otherTeam) {
            return redirect()->back()->withDanger('The player is already a part of a team !');
        }

        $invite = new TeamInvite();
        $invite->team_id = $team->id;
        $invite->tournament_id = $tournament->id;
        $invite->user_id = $request->player_id;

        $invite->cpm = $team->cpm_player_id != $request->user()->id;

        $invite->save();
        
        return redirect()
            ->route('tournaments.teams.manage', $tournament)
            ->withSuccess('You have invited the player successfully.');
    }

    public function accept(Request $request, Tournament $tournament, TeamInvite $invitation) {
        $myTeam = Team::where('tournament_id', $tournament->id)
            ->where('cpm_player_id', $request->user()->id)
            ->orWhere('vq3_player_id', $request->user()->id)
            ->first();

        if ($myTeam) {
            return redirect()->back()->withDanger('You are already a part of a team.');
        }

        $team = $invitation->team()->first();

        if (! $team) {
            return redirect()->back()->withDanger('The team doesnt exist anymore.');
        }

        if ($invitation->cpm) {
            if ($team->cpm_player_id) {
                return redirect()->back()->withDanger('The team is full now !');
            }

            $team->cpm_player_id = $request->user()->id;
        } else {
            if ($team->vq3_player_id) {
                return redirect()->back()->withDanger('The team is full now !');
            }

            $team->vq3_player_id = $request->user()->id;
        }
        
        $team->save();

        $invitation->accepted = true;
        $invitation->save();

        return redirect()
            ->route('tournaments.teams.manage', $tournament)
            ->withSuccess('You have joined the team successfully');
    }

    public function reject(Request $request, Tournament $tournament, TeamInvite $invitation) {
        $invitation->delete();
    }
}
