<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\External\DefragServer;
use App\External\Q3DFServers;
use App\Models\Server;
use App\Models\OnlinePlayer;

use Illuminate\Support\Facades\DB;

class ScrapeServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:servers {all=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape all servers data';

    /**
     * Execute the console command.
     */
    public function handle() {
        $all = ($this->argument('all') == 1) ? true : false;

        $servers = Server::where('visible', true);

        if (! $all) {
            $servers = $servers->where('offline', false);
        }

        $servers = $servers->get();

        $noDataServers = [];

        foreach($servers as $server) {
            $data = $this->getServerData($server);

            if ($data == null || $data['rcon'] == false) {
                $noDataServers[] = [
                    'server'    =>      $server,
                    'data'      =>      $data
                ];
                continue;
            }

            $this->updateServer($server, $data);
        }

        $this->handle_failed_servers($noDataServers);
    }

    public function handle_failed_servers($servers) {
        $q3df_scrapper = new Q3DFServers();

        $q3df_servers = $q3df_scrapper->scrape();

        foreach($servers as $server) {
            $address = $server['server']->ip . ':' . $server['server']->port;
            if (array_key_exists($address, $q3df_servers)) {
                $this->updateServer2($server['server'], $q3df_servers[$address]);
                
                continue;
            }
            
            if ($server['data'] === null) {
                $server['server']->offline = true;
                $server['server']->save();
                continue;
            }

            $this->updateServer($server['server'], $server['data']);
        }

    }

    public function getServerData($server) {
        $result = null;

        try {
            $connection = new DefragServer($server->ip, $server->port);

            if ($server->rconpassword) {
                $result = $connection->getRconData($server->rconpassword);
            } else {
                $result = $connection->getData();
            }

            if ($result == 'Bad rconpassword') {
                $result = $connection->getData();
            }
        } catch (\Exception $e) {
            return null;
        }

        return $result;
    }

    public function updateServer($server, $data) {
        $server->name = trim($data['hostname']);
        $server->defrag = trim($data['defrag']);
        $server->map = strtolower(trim($data['map']));
        $server->offline = false;

        $server->save();

        DB::beginTransaction();

        try {
            OnlinePlayer::where('server_id', $server->id)->delete();

            foreach($data['players'] as $clientId => $player) {
                $onlinePlayer = new OnlinePlayer();
                $onlinePlayer->server_id = $server->id;
    
                $onlinePlayer->name = $this->cleanName($player['name']);
    
                $onlinePlayer->client_id = $clientId;
    
                $onlinePlayer->mdd_id = array_key_exists('mddId', $player) ? intval($player['mddId']) : 0;
    
                $onlinePlayer->nospec = array_key_exists('nospec', $player) ? intval($player['nospec']) : false;
    
                $onlinePlayer->model = array_key_exists('model', $player) ? $player['model'] : 'sarge';
    
                $onlinePlayer->headmodel = array_key_exists('headmodel', $player) ? $player['headmodel'] : 'sarge';
    
                $onlinePlayer->country = array_key_exists('country', $player) ? $player['country'] : '_404';
    
                $score = $this->getPlayerScore($data, $clientId);
    
                $onlinePlayer->follow_num = $score[0];
    
                $onlinePlayer->time = $score[1];
    
                $onlinePlayer->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }


    public function updateServer2($server, $data) {
        $server->name = trim($data['hostname']);
        $server->defrag = trim($data['defrag']);
        $server->map = strtolower(trim($data['map']));
        $server->offline = false;

        $server->save();

        DB::beginTransaction();

        try {
            OnlinePlayer::where('server_id', $server->id)->delete();

            foreach($data['players'] as $player) {
                $onlinePlayer = new OnlinePlayer();
                $onlinePlayer->server_id = $server->id;
    
                $onlinePlayer->name = $player['name'];
    
                $onlinePlayer->client_id = $player['id'];
    
                $onlinePlayer->mdd_id = 0;
    
                $onlinePlayer->nospec = false;
    
                $onlinePlayer->model = 'sarge';
    
                $onlinePlayer->headmodel = 'sarge';
    
                $onlinePlayer->country = $player['country'];
    
                $onlinePlayer->follow_num = $player['follow_num'];
    
                $onlinePlayer->time = array_key_exists('time', $player) ? $player['time'] : 0;
    
                $onlinePlayer->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    public function getPlayerScore($data, $clientId) {
        foreach($data['scores']['players'] as $player) {
            if (! array_key_exists('time', $player)) {
                return [-1, 0];
            }

            if ($player['player_num'] == $clientId) {
                return [$player['follow_num'], $player['time']];
            }
        }

        return [-1, 0];
    }

    public function cleanName($name) {
        $name = trim($name);

        if ($name[0] === '"' && $name[strlen($name) - 1] === '"') {
            $name = substr($name, 1);
            $name = substr($name, 0, -1);
        }

        return $name;
    }
}
