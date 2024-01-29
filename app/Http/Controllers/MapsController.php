<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Record;
use App\Models\User;
use App\Models\Map;

use App\Filters\MapFilters;

class MapsController extends Controller
{
    public function index(Request $request) {
        $users = User::get(['mdd_id', 'name', 'country', 'plain_name']);

        $maps = Map::orderBy('date_added', 'DESC')->paginate(21)->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps)->with('users', $users);
    }

    public function filters(Request $request) {
        $users = User::get(['mdd_id', 'name', 'country', 'plain_name']);
        
        $mapFilters = (new MapFilters())->filter($request);

        $maps = $mapFilters['query'];

        $maps = $maps->paginate(21)->withQueryString();

        $queries = $mapFilters['data'];

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            $paging = ['page' => $maps->lastPage()];

            return redirect()->route('maps.filters', array_merge($paging, $queries));
        }

        return Inertia::render('Maps')
            ->with('maps', $maps)
            ->with('queries', $queries)
            ->with('users', $users);
    }

    public function map(Request $request, $mapname) {
        $column = $request->input('sort', 'date_set');
        $order = $request->input('order', 'DESC');
        $physics = $request->input('physics', 'all');

        if (! in_array($column, ['date_set', 'time', 'physics'])) {
            $column = 'date_set';
        }

        if (! in_array($order, ['DESC', 'ASC'])) {
            $order = 'DESC';
        }

        $map = Map::where('name', $mapname)->firstOrFail();

        $records = Record::where('mapname', $map->name);

        if ($physics == 'cpm' || $physics == 'vq3') {
            $records = $records->where('physics', $physics);
        }

        $records = $records->with('user')->orderBy($column, $order)->paginate(30)->withQueryString();

        return Inertia::render('MapView')
            ->with('map', $map)
            ->with('records', $records);
    }
}
