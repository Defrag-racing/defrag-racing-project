<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\External\DefragServer;
use App\External\Q3DFServers;
use App\Models\Server;
use App\Models\OnlinePlayer;
use App\Models\Record;
use App\Models\MddProfile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScrapeServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:servers {offline=0}';

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
        $offlineFlag = (int) $this->argument('offline');
        $start = microtime(true);
        Log::info('scrape:servers started', ['offline' => $offlineFlag, 'time' => date('c')]);

        try {
            $online = ($offlineFlag == 1) ? false : true;

            $servers = Server::where('visible', true)->where('online', $online)->get();

            $noDataServers = [];

            foreach($servers as $server) {
                $this->info('Scraping ' . $server->ip . ':' . $server->port);

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
        } finally {
            $duration_ms = round((microtime(true) - $start) * 1000);
            Log::info('scrape:servers finished', ['offline' => $offlineFlag, 'duration_ms' => $duration_ms]);
        }
    }

    public function handle_failed_servers($servers) {
        if (count($servers) == 0) {
            return;
        }

        try {
            $q3df_scrapper = new Q3DFServers();

            $q3df_servers = $q3df_scrapper->scrape();
        } catch (\Exception $e) {
            $q3df_servers = [];
        }

        foreach($servers as $server) {
            $address = $server['server']->ip . ':' . $server['server']->port;
            if (array_key_exists($address, $q3df_servers)) {
                $this->updateServer2($server['server'], $q3df_servers[$address]);
                
                continue;
            }

            if ($server['data'] === null) {
                $server['server']->online = false;
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

            // Try getdfstatus first - new engines (KG7X/oDFe extended) send
            // full player info, so no rcon spam on the server console
            $result = $connection->getDfStatusData();

            // Rcon only when getdfstatus failed or came back in the legacy
            // format (no uid/country/model) - there dumpuser is still richer
            if (($result === null || !empty($result['legacy_format'])) && $server->rconpassword) {
                $rconResult = $connection->getRconData($server->rconpassword);

                if ($rconResult == 'Bad rconpassword') {
                    $this->info('Bad rconpassword ' . $server->ip . ':' . $server->port);
                } elseif ($rconResult == 'Rcon not usable') {
                    $this->info('Rcon not usable (missing rs_id ?) ' . $server->ip . ':' . $server->port);
                } elseif ($rconResult !== null) {
                    $result = $rconResult;
                }
            }

            // There is deliberately no third attempt over plain getstatus. It
            // returns names only - no uid, country, model or spectating - so a
            // server answering it would be saved with worse data than one
            // treated as unreachable. Leaving $result null is the better
            // outcome: handle_failed_servers() then tries q3df.org, and only
            // marks the server offline if that fails too.

        } catch (\Exception $e) {
            return null;
        }

        return $result;
    }


    /**
     * A country worth showing a flag for, or null for the placeholders the
     * scrape leaves behind when it does not know.
     */
    private static function validCountry(?string $country): ?string
    {
        return ($country && $country !== '_404' && $country !== 'XX') ? $country : null;
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

    // there is presumtion that we got some data, therefore the server is considered online
    public function updateServer($server, $data) {
        $this->info('Updating ' . $server->ip . ':' . $server->port);
        $this->info('Found ' . count($data['players']) . ' players');

        // Only getdfstatus carries this, and only from an engine new enough to
        // send it - sv_cheats is systeminfo, so the rcon fallback cannot ask
        // for it at all. A server that drops to rcon therefore goes back to
        // "did not say" instead of keeping a stale answer.
        $server->cheats = $data['cheats'] ?? null;

        $server->name = trim($data['hostname']);

        $pattern = '/\^\w/';
        $plainName = preg_replace($pattern, '', trim($data['hostname']));

        $server->plain_name = $plainName;

        $server->defrag = trim($data['defrag']);
        // Don't update defrag_gametype - let admin set it manually in DefragHQ
        $server->map = strtolower(trim($data['map']));
        $server->online = true;

        $bestTime = Record::query()
            ->where('mapname', $server->map)
            ->where('gametype', $this->get_gametype($server->defrag))
            ->orderBy('time', 'ASC')
            ->with('user')
            ->first();

        if ($bestTime) {
            $server->besttime_name = $bestTime->user ? $bestTime->user->name : $bestTime->name;
            $server->besttime_country = self::validCountry($bestTime->user?->country)
                ?? self::validCountry(MddProfile::find($bestTime->mdd_id)?->country)
                ?? $bestTime->country;
            $server->besttime_time = $bestTime->time;
            // The account id and the q3df id are two different numbers and
            // used to share this one column, so an unlinked holder's q3df
            // id was read as somebody else's account. Each has its own now.
            $server->besttime_url = $bestTime->user?->id;
            $server->besttime_mdd_id = $bestTime->mdd_id;
        } else {
            $server->besttime_name = NULL;
            $server->besttime_country = '_404';
            $server->besttime_time = 0;
            $server->besttime_url = '';
            $server->besttime_mdd_id = null;
        }

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

                // Check if time is directly in player array (getdfstatus) or needs to be fetched from scores (rcon)
                if (array_key_exists('time', $player)) {
                    // getdfstatus format
                    $onlinePlayer->time = $player['time'];
                    $onlinePlayer->follow_num = -1; // TODO: parse spectating info
                } else {
                    // rcon format
                    $score = $this->getPlayerScore($data, $clientId);
                    $onlinePlayer->follow_num = $score[0];
                    $onlinePlayer->time = $score[1];
                }

                $onlinePlayer->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    // there is presumtion that we got some data, therefore the server is considered online
    public function updateServer2($server, $data) {
        // q3df.org's listing is the last resort and says nothing about cheats,
        // so this path can only honestly clear it.
        $server->cheats = null;

        $server->name = trim($data['hostname']);

        $pattern = '/\^\w/';
        $plainName = preg_replace($pattern, '', trim($data['hostname']));

        $server->plain_name = $plainName;

        $server->defrag = trim($data['defrag']);
        // Don't update defrag_gametype - let admin set it manually in DefragHQ
        $server->map = strtolower(trim($data['map']));
        $server->online = true;

        $bestTime = Record::query()
            ->where('mapname', $server->map)
            ->where('gametype', $this->get_gametype($server->defrag))
            ->orderBy('time', 'ASC')
            ->with('user')
            ->first();

        if ($bestTime) {
            $server->besttime_name = $bestTime->user ? $bestTime->user->name : $bestTime->name;
            $server->besttime_country = self::validCountry($bestTime->user?->country)
                ?? self::validCountry(MddProfile::find($bestTime->mdd_id)?->country)
                ?? $bestTime->country;
            $server->besttime_time = $bestTime->time;
            // The account id and the q3df id are two different numbers and
            // used to share this one column, so an unlinked holder's q3df
            // id was read as somebody else's account. Each has its own now.
            $server->besttime_url = $bestTime->user?->id;
            $server->besttime_mdd_id = $bestTime->mdd_id;
        } else {
            $server->besttime_name = NULL;
            $server->besttime_country = '_404';
            $server->besttime_time = 0;
            $server->besttime_url = '';
            $server->besttime_mdd_id = null;
        }

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

        if(strlen($name) > 30){
            $name = substr($name, 0, 30) . '...';
        }

        return $name;
    }
}
