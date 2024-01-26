<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class RankingController extends Controller
{
    public function index() {
        return Inertia::render('Ranking');
    }
}
