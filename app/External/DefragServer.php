<?php

namespace App\External;

use Illuminate\Support\Str;

class DefragServer
{
    private $ip;
    private $port;
    private $socket;
    private $connected;

    private $previousData;

    public function __construct ($ip, $port) {
        $this->ip = $ip;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, 0);
        $this->connected = false;

        $this->connect();
    }

    public function connect () {
        if ($this->connected) {
            return;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        socket_connect($this->socket, $this->ip, $this->port);
        $this->connected = true;
    }

    public function getRconData($rconpass) {
        socket_sendto($this->socket, "\xff\xff\xff\xffrcon " . $rconpass . " score\x00", strlen("\xff\xff\xff\xffrcon " . $rconpass . " score\x00"), 0, $this->ip, $this->port);
        $data = "";

        $read = socket_read($this->socket, 8192);

        if(empty($read)){
            return 'Rcon not usable';
        }

        while($read) {
            $data .= $read;
            $read = socket_read($this->socket, 8192);
        }

        if (strpos($data, 'Bad rconpassword') !== false) {
            return 'Bad rconpassword';
        }
    
        $data = substr($data, 10);
        $data = explode("\n", $data);
    
        $scores = [];
        $players = [];
    
        foreach ($data as $line) {
            if (Str::startsWith($line, '<player>')) {
                $player = $this->parseScorePlayer($line);
                $players[$player['clientId']] = $player;

                // Associating player info with scores directly from $data array
                list($playerInfo, $scores) = $this->getPlayerInfo($player, $rconpass, $scores);

                $players[$player['clientId']]['name'] = isset($playerInfo['name']) ? $playerInfo['name'] : $player['name'];
    
                $players[$player['clientId']]['country'] = isset($playerInfo['tld']) ? $playerInfo['tld'] : '_404';
    
                if (isset($playerInfo['color1'])) {
                    $players[$player['clientId']]['nospec'] = ($playerInfo['color1'] == 'nospec' || $playerInfo['color1'] == 'nospecpm');
                } else {
                    $players[$player['clientId']]['nospec'] = false;
                }

                $players[$player['clientId']]['model'] = isset($playerInfo['model']) ? $playerInfo['model'] : 'sarge';
                $players[$player['clientId']]['headmodel'] = isset($playerInfo['headmodel']) ? $playerInfo['headmodel'] : 'sarge';
    
                usleep(200000); // sleep for 0.2 seconds
            } elseif (strpos($line, 'scores') === 0) {
                $scores = $this->parseScores($line);
            }
        }
    
        $result = [
            'players' => $players,
            'map' => explode(':', $data[0])[1],
            'hostname' => explode(':', $data[1])[1],
            'defrag' => explode(':', $data[2])[1],
            'scores' => $scores,
            'rcon'   => true
        ];
    
        return $result;
    }    

    public function getData() {
        socket_sendto($this->socket, "\xff\xff\xff\xffgetstatus\x00", strlen("\xff\xff\xff\xffgetstatus\x00"), 0, $this->ip, $this->port);
        $data = socket_read($this->socket, 4096);

        list($serverData, $players) = $this->parseResponseBody(substr($data, 19));

        $serverData['players'] = $this->parsePlayers($players);

        $playerList = [];
        $i = 0;

        foreach ($serverData['players'] as $player) {
            $playerList[$i] = $player;
            $i++;
        }

        $result = [
            'players' => $playerList,
            'map' => $serverData['mapname'],
            'hostname' => $serverData['sv_hostname'],
            'defrag' => $this->getGameMode($serverData),
            'scores' => [
                'num_players' => count($serverData['players']),
                'speed' => 0,
                'speed_player_num' => 0,
                'speed_player_name' => "",
                'players' => $serverData['players'],
            ],
            'rcon'  =>  false
        ];

        return $result;
    }

    public function getGameMode ($serverData) {
        $physics = ($serverData['df_promode'] == '1') ? 'cpm' : 'vq3';
        $mode = ($serverData['defrag_mode'] == '2') ? '.2' : '';

        return $physics . $mode;
    }

    private function extractKeyValuePair($line) {
        if ($line === '') {
            return [null, null];
        }

        for ($i = 0; $i < strlen($line); $i++) {
            if ($line[$i] === ' ') {
                return [substr($line, 0, $i), trim(substr($line, $i + 1))];
            }
        }
    }

    private function parseScorePlayer ($data) {
        $clientId = explode('<num>', $data)[1];
        $name = explode('<nick>', $data)[1];
        $mddId = explode('<uid>', $data)[1];

        $player = [
            'clientId' => explode('</num>', $clientId)[0],
            'name' => explode('</nick>', $name)[0],
            'mddId' => explode('</uid>', $mddId)[0],
        ];

        $player['logged'] = ($player['mddId'] != '0');

        return $player;
    }

    public function getPlayerInfo($player, $rconpass, $scores = []) {
        socket_sendto($this->socket, "\xff\xff\xff\xffrcon " . $rconpass . " dumpuser " . $player['clientId'] . "\x00", strlen("\xff\xff\xff\xffrcon " . $rconpass . " dumpuser " . $player['clientId'] . "\x00"), 0, $this->ip, $this->port);
        $data = socket_read($this->socket, 4096);

        if (empty($scores) && strpos($data, 'scores ') !== false) {
            foreach (explode("\n", substr($data, 28)) as $line) {
                if (strpos($line, 'scores') === 0) {
                    $scores = $this->parseScores($line);
                }
            }
        }

        if (strpos($data, "print\nscores") !== false || strpos($data, "print\n<player>") !== false) {
            return $this->getPlayerInfo($player, $rconpass, $scores);
        }

        if ($data == $this->previousData) {
            return $this->getPlayerInfo($player, $rconpass, $scores);
        }

        $this->previousData = $data;

        $data = explode("\n", substr($data, 28));

        $result = [];

        foreach ($data as $line) {
            list($key, $value) = $this->extractKeyValuePair($line);
            $result[$key] = $value;
        }
        return [$result, $scores];
    }

    public function parseScores($data) {
        $scores = [];
        $parsed = explode('"', str_replace('scores ', '', $data));
        $data = explode(' ', trim($parsed[0]));

        $data[] = $parsed[1];

        $data = array_merge($data, explode(' ', trim($parsed[2])));

        $scores['num_players'] = (int) array_shift($data);
        $scores['speed'] = (int) array_shift($data);
        $scores['speed_player_num'] = (int) array_shift($data);
        $scores['speed_player_name'] = array_shift($data);

        $scores['players'] = [];

        if (count($data) <= 1) {
            return $scores;
        }

        while (count($data) > 0) {
            $player = [
                'player_num' => (int) array_shift($data),
                'time' => (int) array_shift($data),
                'ping' => (int) array_shift($data),
                'follow_num' => (int) array_shift($data),
            ];

            $scores['players'][] = $player;
        }

        return $scores;
    }

    public function parsePlayers($data) {
        $players = [];
        foreach ($data as $line) {
            if ($line == '') {
                continue;
            }

            $line = utf8_decode($line);

            $parts = explode(' ', $line, 3);

            if (count($parts) < 3) {
                continue;
            }

            $player = array_combine(['score', 'ping', 'name'], $parts);
            $players[] = ['name' => $player['name']];
        }

        return $players;
    }

    public function parseResponseBody($body) {
        $i = 0;
        $keys = [];
        $values = [];
        while (strpos($body, '\\') === 0) {
            if (strpos($body, '\\', 1) !== false) {
                $elementEnd = strpos($body, '\\', 1);
            } elseif (strpos($body, '\n', 1) !== false) {
                $elementEnd = strpos($body, '\n', 1);
            } else {
                break;
            }

            $element = substr($body, 1, $elementEnd - 1);
            if ($i % 2 == 0) {
                $keys[] = utf8_decode($element);
            } else {
                $values[] = utf8_decode($element);
            }

            $body = substr($body, $elementEnd);
            $i++;
        }

        $lines = explode("\n", $body);

        $diff = count($keys) - count($values);

        for($i = 0; $i < $diff; $i++) {
            if ($diff > 0) {
                array_pop($keys);
            } else {
                array_pop($values);
            }
        }

        return [array_combine($keys, $values), $lines];
    }
}