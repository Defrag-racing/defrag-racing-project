<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Round;
use App\Models\Tournament;

class ResultsDownloadController extends Controller
{
    public function results(Tournament $tournament, Round $round) {
        $file = $round->buildResultsZip(false);

        if (! $file) {
            abort(404);
        }

        return response()->download(storage_path('app/results/' . $file));
    }

    public function anonymous(Tournament $tournament, Round $round) {
        $file = $round->buildResultsZip(true);

        if (! $file) {
            abort(404);
        }

        return response()->download(storage_path('app/results/' . $file));
    }
}
