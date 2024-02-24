<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use Carbon\Carbon;

class TournamentsController extends Controller {
    public function index(Request $request) {
        $tournaments = Tournament::all();

        $activeTournaments = [];
        $upcomingTournaments = [];
        $pastTournaments = [];

        $tournaments->each(function($tournament) use ($request, &$activeTournaments, &$upcomingTournaments, &$pastTournaments) {
            $start_date = Carbon::parse($tournament->start_date);
            $end_date = Carbon::parse($tournament->end_date);
            $now = Carbon::now();

            if ($now->between($start_date, $end_date) && $now->gte($start_date)) {
                array_push($activeTournaments, $tournament);
            }

            if ($now->lt($start_date)) {
                array_push($upcomingTournaments, $tournament);
            }

            if ($now->gt($end_date)) {
                array_push($pastTournaments, $tournament);
            }

            $organizer = Organizer::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $request->user()->id);

            $tournament->isOrganizer = $organizer->where('role', '!=', 'validator')->exists();
            $tournament->isValidator = $organizer
                                        ->where('role', 'validator')
                                        ->orWhere('role', 'admin')
                                        ->exists();
        });

        return Inertia::render('Tournaments/Index')
            ->with('tournaments', $tournaments)
            ->with('activeTournaments', $activeTournaments)
            ->with('upcomingTournaments', $upcomingTournaments)
            ->with('pastTournaments', $pastTournaments);
    }

    public function create() {
        return Inertia::render('Tournaments/Create');
    }

    public function store(Request $request) {
        $request->validate([
            'name' => ['required', 'max:255', 'string'],
            'description' => ['required', 'string'],
            'rules' => ['required', 'string'],
            'photo' => ['required', 'image'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'has_teams' => ['required', 'boolean'],
            'prize_pool' => ['required', 'numeric'],
            'has_donations' => ['required', 'boolean']
        ]);

        $slug = \Str::slug($request->name);

        if (Tournament::where('slug', $slug)->exists()) {
            return Inertia::render('Tournaments/Create')->with('error', 'A tournament with that name already exists');
        }

        $start_date = Carbon::parse($request->start_date);
        $end_date = Carbon::parse($request->end_date);
        $now = Carbon::now();

        if ($start_date->lt($now)) {
            return Inertia::render('Tournaments/Create')->with('error', 'Start date must be in the future');
        }

        if ($end_date->lt($start_date)) {
            return Inertia::render('Tournaments/Create')->with('error', 'End date must be after start date');
        }

        $tournament = new Tournament;
        $tournament->name = $request->name;
        $tournament->description = $request->description;
        $tournament->rules = $request->rules;
        $tournament->slug = $slug;
        $tournament->start_date = $request->start_date;
        $tournament->end_date = $request->end_date;
        $tournament->has_teams = $request->has_teams;
        $tournament->prize_pool = $request->prize_pool;
        $tournament->has_donations = $request->has_donations;
        $tournament->creator = $request->user()->id;

        $tournament->image = $request->photo->store('tournaments', 'public');

        if ($request->has('trailer')) {
            $tournament->trailer = $request->trailer;
        }

        $tournament->save();

        $organizer = new Organizer;
        $organizer->tournament_id = $tournament->id;
        $organizer->user_id = $request->user()->id;
        $organizer->role = 'admin';
        $organizer->save();

        return redirect()->route('tournaments.index');
    }
}
