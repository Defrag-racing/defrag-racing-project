<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\External\DefragServer;
use App\Models\Server;
use App\Models\OnlinePlayer;

use Illuminate\Support\Facades\DB;

class UpdateServersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-servers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all servers data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $servers = Server::where('offline', false)->where('visible', true)->get();

        foreach($servers as $server) {
            $data = $this->getServerData($server);

            $this->updateServer($server, $data);
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

    public function getPlayerScore($data, $clientId) {
        foreach($data['scores']['players'] as $player) {
            if (! array_key_exists('time', $player)) {
                return [-1, 0];
            }

            return [$player['follow_num'], $player['time']];
        }
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
