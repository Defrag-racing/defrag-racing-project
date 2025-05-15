<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

class ServersController extends Controller
{
    public function index() {
        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['onlinePlayers.spectators'])
            ->orderBy('plain_name', 'asc')
            ->get();

        $servers->each(function ($server) {
            $server->map = strtolower($server->map);
            $server->load('mapdata');
        });

        $servers = $this->sortServers($servers);

        $servers = $servers->values()->all();

        return Inertia::render('Servers')->with('servers', $servers);
    }

    function sortServers($servers) {
        $servers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });

        return $servers;
    }
}


