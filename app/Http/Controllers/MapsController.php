<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Map;
use Inertia\Inertia;

class MapsController extends Controller
{
    public function index(Request $request) {
        $maps = Map::orderBy('date_added', 'DESC')->paginate(21);

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps);
    }

    public function map($map) {

    }
}
