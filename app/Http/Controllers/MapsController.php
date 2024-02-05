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
        $users = User::orderBy('plain_name', 'ASC')->whereNot('mdd_id', NULL)->get(['mdd_id', 'name', 'country', 'plain_name']);

        $maps = Map::orderBy('id', 'DESC')->paginate(21)->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps)->with('users', $users);
    }

    public function filters(Request $request) {
        $users = User::orderBy('plain_name', 'ASC')->whereNot('mdd_id', NULL)->get(['mdd_id', 'name', 'country', 'plain_name']);
        
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
        $gametype = $request->input('gametype', 'run');
        $cpmGametype = $gametype . '_cpm';
        $vq3Gametype = $gametype . '_vq3';


        if ($request->user()->mdd_id) {
            $my_cpm_record = Record::where('mapname', $mapname)->where('mdd_id', $request->user()->mdd_id)->where('gametype', $cpmGametype)->with('user')->first();
            $my_vq3_record = Record::where('mapname', $mapname)->where('mdd_id', $request->user()->mdd_id)->where('gametype', $vq3Gametype)->with('user')->first();
        } else {
            $my_cpm_record = null;
            $my_vq3_record = null;
        }

        if (! in_array($column, ['date_set', 'time'])) {
            $column = 'date_set';
        }

        if (! in_array($order, ['DESC', 'ASC'])) {
            $order = 'DESC';
        }

        $map = Map::where('name', $mapname)->firstOrFail();

        $cpmRecords = Record::where('mapname', $map->name);

        $cpmRecords = $cpmRecords->where('gametype', $cpmGametype);

        $cpmRecords = $cpmRecords->with('user')->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'cpmPage')->withQueryString();

        $vq3Records = Record::where('mapname', $map->name);

        $vq3Records = $vq3Records->where('gametype', $vq3Gametype);

        $vq3Records = $vq3Records->with('user')->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'vq3Page')->withQueryString();

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
            ->with('vq3Records', $vq3Records)
            ->with('my_cpm_record', $my_cpm_record)
            ->with('my_vq3_record', $my_vq3_record);
    }
}
