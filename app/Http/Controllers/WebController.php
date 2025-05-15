<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\Map;
use App\Models\Server;
use App\Models\Tournament;

class WebController extends Controller
{
    public function home() {
        $announcement = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->first();

        $maps = Map::query()
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get();

        $tournaments = Tournament::query()
            ->where('start_date', '<=', now())
            ->orderBy('start_date', 'DESC')
            ->limit(3)
            ->get();

        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['mapdata', 'onlinePlayers.spectators'])
            ->orderBy('plain_name', 'asc')
            ->get();

        $servers = $this->sortServers($servers);

        $servers = $servers->values()->take(3);

        return Inertia::render('Home')
            ->with('announcement', $announcement)
            ->with('maps', $maps)
            ->with('servers', $servers)
            ->with('tournaments', $tournaments);
    }

    function sortServers($servers) {
        $servers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });

        return $servers;
    }

    public function flags($flag) {
        $fileResponse = new BinaryFileResponse(public_path() . '/images/flags/_404.png');

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

    public function gettingstarted() {
        return Inertia::render('GettingStarted');
    }
}
