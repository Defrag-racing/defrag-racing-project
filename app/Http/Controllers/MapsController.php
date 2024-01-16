<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Map;
use Inertia\Inertia;

class MapsController extends Controller
{
    public function index() {
        $maps = Map::orderBy('date_added', 'DESC')->paginate(20);

        return Inertia::render('Maps')->with('maps', $maps);
    }

    public function map($map) {

    }
}
