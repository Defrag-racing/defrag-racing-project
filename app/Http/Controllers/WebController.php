<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Server;
use App\Models\Map;

class WebController extends Controller
{
    function home() {
        // session()->flash('flash.banner', 'Defrag Racing is under maintenance right now !');
        // session()->flash('flash.bannerStyle', 'danger');

        return Inertia::render('Home');
    }

    function servers() {
        $servers = Server::where('offline', false)->where('visible', true)->with('mapdata')->with('onlinePlayers')->get();


        $sortedServers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });

        $sortedServersArray = $sortedServers->values()->all();

        \Log::debug($sortedServersArray);


        return Inertia::render('Servers')->with('servers', $sortedServersArray);
    }
}
