<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Map;
use App\Models\Record;

use Illuminate\Database\Eloquent\Builder;

class MapsController extends Controller
{
    public function index(Request $request) {
        $maps = Map::orderBy('date_added', 'DESC')->paginate(21)->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $maps);
    }

    public function filters(Request $request) {
        $maps = Map::orderBy('date_added', 'DESC');

        $queries = [];

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        if ($request->filled('search')) {
            $maps = $maps->where('name', 'LIKE', '%' . $request->search . '%');
            $queries['search'] = $request->search;
        }

        if ($request->filled('author')) {
            $maps = $maps->where('author', 'LIKE', '%' . $request->author . '%');
            $queries['author'] = $request->author;
        }

        if ($request->filled('physics')) {
            if (count($request->physics) == 1) {
                $maps = $maps->where(function (Builder $query) use($request) {
                    $query->where('physics', trim($request->physics[0]))
                        ->orWhere('physics', 'all');
                });
            }
            $queries['physics'] = $request->physics;
        }

        if ($request->filled('gametype')) {
            if (count($request->gametype) > 0) {
                $maps = $maps->whereIn('gametype', $request->gametype);
            }
            $queries['gametype'] = $request->gametype;
        }

        $maps = $maps->paginate(21)->withQueryString();

        return Inertia::render('Maps')
            ->with('maps', $maps)
            ->with('queries', $queries);
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
