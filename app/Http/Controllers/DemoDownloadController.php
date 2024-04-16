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

        $me = $request->user()?->id;

        if ($round->start_date > Carbon::now()) {
            return redirect()->route('tournaments.index');
        }

        if ($round->published == true && $round->end_date < Carbon::now() && $round->results == true) {
            return Storage::download($demo->file, $demo->filename);
        }

        if ($me && $me === $user->id) {
            return Storage::download($demo->file, $demo->filename);
        }

        if ($me && $round->tournament->isOrganizer($me) || $round->tournament->isValidator($me)) {
            return Storage::download($demo->file, $demo->filename);
        }

        return redirect()->route('tournaments.index');
    }
}
