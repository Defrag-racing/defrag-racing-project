<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

class ServersController extends Controller
{
    public function index() {
        $servers = Server::where('offline', false)
            ->where('visible', true)
            ->with(['mapdata', 'onlinePlayers.spectators'])
            ->orderBy('plain_name', 'asc')
            ->get();

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


