<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Map;
use App\Models\Record;

class MapsController extends Controller
{
    public function index(Request $request) {
        $maps = Map::orderBy('date_added', 'DESC')->paginate(21);

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps);
    }

    public function map(Request $request, $mapname) {
        $column = $request->input('sort', 'date_set');
        $order = $request->input('order', 'DESC');
        $physics = $request->input('physics', 'all');

        if (! in_array($column, ['date_set', 'time', 'physics'])) {
            $column = 'date_set';
        }

        if (! in_array($order, ['DESC', 'ASC'])) {
            $sort = 'DESC';
        }

        $map = Map::where('name', $mapname)->firstOrFail();

        $records = Record::where('mapname', $map->name);

        if ($physics == 'cpm' || $physics == 'vq3') {
            $records = $records->where('physics', $physics);
        }

        $records = $records->with('user')->orderBy($column, $order)->paginate(30);

        $vq3record = Record::where('mapname', $map->name)->where('physics', 'vq3')->orderBy('time', 'ASC')->first();
        $cpmrecord = Record::where('mapname', $map->name)->where('physics', 'cpm')->orderBy('time', 'ASC')->first();

        return Inertia::render('MapView')
            ->with('map', $map)
            ->with('records', $records)
            ->with('vq3record', $vq3record)
            ->with('cpmrecord', $cpmrecord);
    }
}
