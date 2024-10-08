<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Server;
use App\Models\Map;
use App\Models\User;
use App\Models\Record;
use App\Models\MddProfile;
use App\Models\OldtopRecord;
use Illuminate\Support\Facades\DB;

class ImportDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import {function} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports Json data to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $func = $this->argument('function');
        $file = 'externaldata/' . $this->argument('file');

        // Your command logic here
        $this->info("Importing data using: $func");

        $data = $this->get_json_data($file);

        if ($data === null) {
            $this->error('Invalid Json Data in: ' . $file);
            return;
        }

        $this->$func($data);
    }

    public function get_json_data($file) {
        try {
            $jsonData = file_get_contents($file);

            $data = json_decode($jsonData, true);

            return $data;
        } catch(\Exception $e) {
            $this->error($e->getMessage());
            return null;
        }
    }

    public function servers($data) {
        foreach($data as $element) {
            $newElement = array_merge([], $element);

            $map = json_decode($element['map'])->name;
            $defrag = json_decode($element['state'])->defrag;

            $newElement['map'] = $map;
            $newElement['defrag'] = $defrag;
            $newElement['besttime_name'] = NULL;
            $newElement['besttime_country'] = '_404';
            $newElement['besttime_time'] = 0;
            $newElement['besttime_url'] = '';

            $server = new Server($newElement);
            $server->save();
        }
    }

    public function maps($data) {
        foreach($data as $element) {
            $newElement = array_merge([], $element);

            $newElement['author'] = $element['author_name'];
            $newElement['pk3'] = $element['pk3_file'];
            $newElement['date_added'] = $element['date_added_ws'];
            $newElement['visible'] = $element['is_visible'];

            $map = new Map($newElement);
            $map->save();
        }
    }

    public function users($data) {
        $pattern = '/\^\w/';

        foreach($data as $element) {
            $plainName = preg_replace($pattern, '', $element['profile']['display_name']);

            $newElement = [
                'id'        =>      $element['id'],
                'oldhash'        =>      $element['password'],
                'admin'        =>      $element['admin'],
                'username'        =>      $element['username'],
                'email'        =>      $element['email'],
                'password'        =>      'NOPASS',
                'name'        =>      $element['profile']['display_name'],
                'country'        =>      $element['profile']['country'],
                'twitch_name'        =>      $element['profile']['twitch_name'],
                'twitter_name'        =>      $element['profile']['twitter_name'],
                'discord_name'        =>      $element['profile']['discord_name'],
                'model'        =>      $element['profile']['model'],
                'mdd_id'        =>      $element['profile']['mdd_id'],
                'plain_name'        =>  $plainName,
                'created_at'        =>  $element['date_joined']
            ];

            $user = new User($newElement);
            $user->save();
        }
    }

    public function records($data) {
        DB::beginTransaction();

        foreach($data as $element) {
            $newElement = [
                'name'        =>      $element['player_name'],
                'country'        =>      $element['player_country'],
                'physics'        =>      $element['physics'],
                'mode'        =>      $element['mode'],
                'gametype'        =>      $element['gametype'],
                'time'        =>      $element['time'],
                'date_set'        =>      $element['date_set'],
                'user_id'        =>      $element['player_id'],
                'mdd_id'        =>      $element['player_mdd_id'],
                'mapname'        =>      $element['mapname'],
            ];

            $record = new Record($newElement);
            $record->save();
        }

        DB::commit();
    }

    public function mdd($data) {
        $pattern = '/\^\w/';

        foreach($data as $element) {
            $plainName = preg_replace($pattern, '', $element['display_name']);

            $newElement = [
                'id'        =>      $element['id'],
                'name'        =>      $element['display_name'],
                'plain_name'        =>      $plainName,
                'country'        =>      $element['country'],
                'model'        =>      $element['model'],
                'headmodel'        =>      $element['headmodel']
            ];

            $mdd = new MddProfile($newElement);
            $mdd->save();
        }
    }

    public function oldtop($data) {
        $pattern = '/\^\w/';

        DB::beginTransaction();

        foreach($data as $element) {
            $physic = intval($element['physic']);
            $gametype = '';

            if ($physic == '0') {
                $gametype = 'run_vq3';
            }

            if ($physic == '1') {
                $gametype = 'run_cpm';
            }
            
            if ($physic > 10 && $physic < 18) {
                $ctf = ($physic - 10);
                $gametype = 'ctf' . $ctf . '_vq3';
            }
            
            if ($physic > 20 && $physic < 28) {
                $ctf = ($physic - 20);
                $gametype = 'ctf' . $ctf . '_cpm';
            }

            if ($gametype === '') {
                $gametype = 'unkown';
            }

            $newElement = [
                'id'                =>      intval($element['id']),
                'name'              =>      $element['visname'],
                'plain_name'        =>      $element['playername'],
                'mapname'           =>      $element['mapname'],
                'country'           =>      '_404',
                'time'              =>      intval($element['rectime']),
                'physic'            =>      $physic,
                'rank'              =>      intval($element['rank']),
                'date_set'          =>      $element['ts'],
                'gametype'          =>      $gametype
            ];

            $oldtop = new OldtopRecord($newElement);
            $oldtop->save();
        }

        DB::commit();
    }
}
