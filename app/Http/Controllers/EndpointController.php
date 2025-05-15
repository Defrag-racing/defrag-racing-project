<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Server;

class EndpointController extends Controller {
    public function index() {
        $servers = Server::where('online', true)
            ->where('visible', true)
            ->with(['mapdata', 'onlinePlayers.spectators'])
            ->get();

        $servers = $this->sortServers($servers);

        $servers = $servers->values()->all();
        
        $resultServers = [
            "active"    =>  [],
            "empty"     =>  []
        ];

        foreach($servers as $server) {
            $serverData = $this->getServerData($server);

            if (count($serverData['scores']) == 0) {
                $resultServers["empty"][$server->ip . ":" . $server->port] = $serverData;
            } else {
                $resultServers["active"][$server->ip . ":" . $server->port] = $serverData;
            }
        }

        return $resultServers;
    }

    function sortServers($servers) {
        $servers = $servers->sortByDesc(function ($server) {
            return $server->onlinePlayers->count();
        });

        return $servers;
    }

    function getServerData($server) {
        $result = [];

        $result['map'] = $server->map;
        $result['hostname'] = $server->name;
        $result['defrag'] = $server->defrag;
        $result['address'] = $server->ip . ":" . $server->port;
        $result['timestamp'] = now()->format('Y-m-d H:i:s');

        $result['players'] = [];
        $result['scores'] = [
            'players'           =>      []
        ];

        foreach($server->onlinePlayers as $player) {
            $playerData = [];
            $score = [];

            $playerData['clientId'] = $player->client_id;
            $playerData['name'] = $player->name;
            $playerData['logged'] = ($player->mdd_id > 0);
            $playerData['mddId'] = $player->mdd_id;
            $playerData['country'] = $player->country;
            $playerData['nospec'] = $player->nospec;
            $playerData['model'] = $player->model;
            $playerData['headmodel'] = $player->headmodel;

            $score['player_num'] = $player->client_id;
            $score['follow_num'] = $player->follow_num;
            $score['time'] = $player->time;

            $result['players'][$player->client_id] = $playerData;
            $result['scores']['players'][] = $score;
        }

        $result['scores']['num_players'] = count($server->onlinePlayers);

        return $result;
    }

    function getScoresData($server) {

    }
}
