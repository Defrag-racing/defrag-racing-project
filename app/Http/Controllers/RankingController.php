<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\PlayersRatings;

class RankingController extends Controller
{
    public function index() {

        $players_ratings = PlayersRatings::query();

        $players_ratings = $players_ratings->paginate(30)->withQueryString();

        return Inertia::render('Ranking')
            ->with('players_ratings', $players_ratings);
    }
}
