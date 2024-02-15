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
        // return response()->file(public_path() . '/images/flags/_404.png');

        $fileResponse = new BinaryFileResponse(public_path() . '/images/flags/_404.png');

        return $fileResponse;
    }

    public function thumbs($image) {
        // return response()->file(public_path() . '/images/unknown.jpg');

        $fileResponse = new BinaryFileResponse(public_path() . '/images/unknown.jpg');

        return $fileResponse;
    }

    public function map($name) {
        $map = Map::where('name', $name)->first();

        if ($map && $map->pk3) {
            $parts = explode('/', $map->pk3);
            $url = "https://dl.defrag.racing/downloads/maps/" . end($parts);;

            return redirect($url);
        }

        abort(404);
    }
}
