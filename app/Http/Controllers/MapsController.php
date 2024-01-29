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
        $column = $request->input('sort', 'time');
        $order = $request->input('order', 'ASC');
        $cpmGametype = $request->input('cpmGametype', 'run_cpm');
        $vq3Gametype = $request->input('vq3Gametype', 'run_vq3');

        if (! in_array($column, ['date_set', 'time'])) {
            $column = 'date_set';
        }

        if (! in_array($order, ['DESC', 'ASC'])) {
            $order = 'DESC';
        }

        $map = Map::where('name', $mapname)->firstOrFail();

        $cpmRecords = Record::where('mapname', $map->name);

        $cpmRecords = $cpmRecords->where('gametype', $cpmGametype);

        $cpmRecords = $cpmRecords->with('user')->orderBy($column, $order)->paginate(30, ['*'], 'cpmPage')->withQueryString();

        $vq3Records = Record::where('mapname', $map->name);

        $vq3Records = $vq3Records->where('gametype', $vq3Gametype);

        $vq3Records = $vq3Records->with('user')->orderBy($column, $order)->paginate(30, ['*'], 'vq3Page')->withQueryString();

        $cpmPage = ($request->has('cpmPage')) ? min($request->cpmPage, $cpmRecords->lastPage()) : 1;

        $vq3Page = ($request->has('vq3Page')) ? min($request->vq3Page, $vq3Records->lastPage()) : 1;

        if ($request->has('vq3Page') && $request->get('vq3Page') > $vq3Records->lastPage()) {
            return redirect()->route('maps.map', ['vq3Page' => $vq3Records->lastPage(), 'mapname' => $mapname, 'cpmPage' => $cpmPage]);
        }

        if ($request->has('cpmPage') && $request->get('cpmPage') > $cpmRecords->lastPage()) {
            return redirect()->route('maps.map', ['cpmPage' => $cpmRecords->lastPage(), 'mapname' => $mapname, 'vq3Page' => $vq3Page]);
        }

        return Inertia::render('MapView')
            ->with('map', $map)
            ->with('cpmRecords', $cpmRecords)
            ->with('vq3Records', $vq3Records);
    }
}
