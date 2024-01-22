<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Map;
use App\Models\Record;

class RecordsController extends Controller
{
    public function index(Request $request) {
        $physics = $request->input('physics', 'all');

        $records = Record::query();

        if ($physics == 'cpm' || $physics == 'vq3') {
            $records = $records->where('physics', $physics);
        }

        $records = $records->with('user')->with('map')->orderBy('date_set', 'DESC')->paginate(30)->withQueryString();

        return Inertia::render('RecordsView')
            ->with('records', $records);
    }
}
