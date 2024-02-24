<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;

class TournamentsController extends Controller {
    public function index() {
        $tournaments = Tournament::all();

        return Inertia::render('Tournaments/Index')->with('tournaments', $tournaments);
    }

    public function create() {
        $tournaments = Tournament::all();

        return Inertia::render('Tournaments/Create')->with('tournaments', $tournaments);
    }
}
