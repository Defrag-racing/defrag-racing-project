<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\Round;
use App\Models\Demo;

use Carbon\Carbon;

class ValidateDemosController extends Controller {
    public function index (Tournament $tournament) {
        $rounds = Round::where('tournament_id', $tournament->id)
            ->where('start_date', '<', Carbon::now())
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('Tournaments/Management/Validation/Index')
                ->with('tournament', $tournament)
                ->with('rounds', $rounds);
    }

    public function round (Tournament $tournament, Round $round) {
        if ($round->start_date > Carbon::now()) {
            return redirect()->route('tournaments.validation.index', $tournament);
        }

        $round->load('vq3_demos.user');
        $round->load('cpm_demos.user');

        $unvalidated_vq3 = Demo::where('round_id', $round->id)
            ->where('physics', 'vq3')
            ->where('approved', false)
            ->where('rejected', false)
            ->count();

        $unvalidated_cpm = Demo::where('round_id', $round->id)
            ->where('physics', 'cpm')
            ->where('approved', false)
            ->where('rejected', false)
            ->count();

        return Inertia::render('Tournaments/Management/Validation/Round')
                ->with('tournament', $tournament)
                ->with('round', $round)
                ->with('unvalidated_vq3', $unvalidated_vq3)
                ->with('unvalidated_cpm', $unvalidated_cpm);
    }

    public function approve(Tournament $tournament, Round $round, Demo $demo) {
        $demo->approved = true;
        $demo->rejected = false;
        $demo->save();

        return back();
    }

    public function reject(Tournament $tournament, Round $round, Demo $demo, Request $request) {
        $demo->approved = false;
        $demo->rejected = true;
        $demo->reason = $request->reason ?? '';
        $demo->save();

        return back();
    }
}
