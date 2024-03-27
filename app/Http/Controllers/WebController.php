<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\Map;

class WebController extends Controller
{
    public function home() {
        $announcements = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->get();

        return Inertia::render('Home')->with('announcements', $announcements);
    }

    public function flags($flag) {
        return response()->file(public_path() . '/images/flags/_404.png');

        return $fileResponse;
    }

    public function thumbs($image) {
        return response()->file(public_path() . '/images/unknown.jpg');

        return $fileResponse;
    }

    public function map($name) {
        $map = Map::where('name', $name)->first();

        if ($map && $map->pk3) {
            $parts = explode('/', $map->pk3);
            $filename = end($parts);

            $url = "https://dl.defrag.racing/downloads/maps/" . $filename;

            $temp = tempnam(sys_get_temp_dir(), $filename);
            copy($url, $temp);


            return response()->download($temp, $filename)->deleteFileAfterSend(true);
        }

        abort(404);
    }
}
