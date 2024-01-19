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
            ->with(['mapdata.records.user', 'onlinePlayers.spectators'])
            ->get();

        $servers = $this->sortServers($servers);

        $servers = $servers->values()->all();

        return Inertia::render('Servers')->with('servers', $servers);
    }

    function get_gametype($physics) {
        if ($physics == 'cpm' || $physics == 'vq3') {
            return 'run_' . $physics;
        }

        if (strpos($physics, '.') !== false) {
            $parts = explode('.', $physics);

            return 'ctf' . $parts[1] . '_' . $parts[0];
        }
    }

    function sortServers($servers) {
        $servers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });


        $servers->each(function ($server) {
            if ($server->mapdata == null) {
                $server->bestrecord = null;
                return;
            }

            $bestRecord = null;

            $server->mapdata->records->each(function ($record) use($server, &$bestRecord) {
                if ($record->gametype == $this->get_gametype($server->defrag) && $server->type == 'run') {
                    if ($bestRecord == null or $record->time <= $bestRecord->time) {
                        $bestRecord = $record;
                    }
                }
            });

            $server->bestrecord = $bestRecord;
        });

        return $servers;
    }
}


