<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\RoundMap;
use Illuminate\Support\Facades\Storage;
use App\Models\Round;
use Carbon\Carbon;

class MapDownloadController extends Controller
{
    public function download(RoundMap $map, Request $request) {
        $round = $map->round;

        if ($round->start_date > Carbon::now()) {
            return redirect()->route('tournaments.index');
        }

        if ($round->published == true) {
            return Storage::download($map->pk3, $map->download_name);
        }

        if ($round->tournament->isOrganizer($request->user()->id) || $round->tournament->isValidator($request->user()->id)) {
            return Storage::download($map->pk3, $map->download_name);
        }

        return redirect()->route('tournaments.index');
    }
}
