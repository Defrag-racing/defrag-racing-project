<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;
use Carbon\Carbon;

class TournamentsController extends Controller {
    public function index(Request $request) {
        $tournaments = Tournament::query()
            ->where('published', true)
            ->orWhere('creator', $request->user()->id)
            ->get();

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
                ->where('user_id', $request->user()->id)
                ->where('role', '!=', 'validator')
                ->exists();

            $validator = Organizer::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'validator')
                ->orWhere('role', 'admin')
                ->exists();


            $tournament->isOrganizer = $organizer;

            $tournament->isValidator = $validator;
        });

        return Inertia::render('Tournaments/Index')
            ->with('tournaments', $tournaments)
            ->with('activeTournaments', $activeTournaments)
            ->with('upcomingTournaments', $upcomingTournaments)
            ->with('pastTournaments', $pastTournaments);
    }

    public function show(Tournament $tournament, Request $request) {
        $tournament->isOrganizer = $tournament->isOrganizer($request->user()->id);
        $tournament->isValidator = $tournament->isValidator($request->user()->id);
        
        $tournament->load('organizers.user');

        return Inertia::render('Tournaments/Tournament/Overview')
            ->with('tournament', $tournament);
    }

    public function rules(Tournament $tournament, Request $request) {
        return Inertia::render('Tournaments/Tournament/Rules')
            ->with('tournament', $tournament);
    }
}
