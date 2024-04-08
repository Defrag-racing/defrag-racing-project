<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Demo;
use Illuminate\Support\Facades\Storage;
use App\Models\Round;
use Carbon\Carbon;

class DemoDownloadController extends Controller {
    public function download(Demo $demo, Request $request) {
        $round = $demo->round;
        $user = $demo->user;

        if ($round->start_date > Carbon::now()) {
            return redirect()->route('tournaments.index');
        }

        if ($round->published == true && $round->end_date < Carbon::now() && $round->results == true) {
            return Storage::download($demo->file, $demo->filename);
        }

        if ($request->user()?->id === $user->id) {
            return Storage::download($demo->file, $demo->filename);
        } else {
            return redirect()->route('tournaments.index');
        }

        if ($round->tournament->isOrganizer($request->user()->id) || $round->tournament->isValidator($request->user()->id)) {
            return Storage::download($demo->file, $demo->filename);
        }

        return redirect()->route('tournaments.index');
    }
}
