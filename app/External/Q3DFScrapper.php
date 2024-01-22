<?php

namespace App\External;

use \DOMDocument;
use \DOMXPath;

class Q3DFScrapper {
    protected $url = "https://q3df.org/serverlist";
    protected $xpath;

    public function scrape() {
        $servers = [];

        $response = file_get_contents($this->url);

        if ($response === false) {
            return [];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($response);

        libxml_clear_errors();

        $this->xpath = new DOMXPath($dom);

        $serverItems = $this->xpath->query('//div[contains(@class, "server-item")]');

        foreach ($serverItems as $serverItem) {
            $server = $this->get_server($serverItem);

            $servers[$server['address']] = $this->get_server($serverItem);
        }

        return $servers;
    }

    private function get_server($serverItem) {
        $hostname = $this->xpath->query('.//div[@class="server-head"]/span[@class="visname"]', $serverItem)->item(0);

        $map_info = $this->xpath->query('.//div[@class="server-map-info"]/ul', $serverItem)->item(0);

        $li = $map_info->getElementsByTagName('li');

        $server_data = [
            'address' => $li[0]->getElementsByTagName('a')[0]->textContent,
            'map' => $li[1]->getElementsByTagName('a')[0]->textContent,
            'defrag' => $li[2]->textContent,
            'hostname' => $this->get_q3_string($hostname),
            'players' => []
        ];
        
        $players_section = $this->xpath->query('.//div[@class="server-players"]', $serverItem)->item(0);

        $players_table = $players_section->childNodes->item(1);
        $spectators_table = $players_section->childNodes->item(3);

        $players = $this->get_players($players_table);

        $spectators = $this->get_spectators($spectators_table);

        $server_data['players'] = $this->process_spectators($players, $spectators);

        return $server_data;
    }

    private function process_spectators($players, $spectators) {
        $id = count($players);

        foreach($spectators as $spectator) {
            foreach($players as $player) {
                if ($spectator['spectates'] === $player['name']) {
                    $spectator['follow_num'] = $player['id'];
                    break;
                }
            }

            $spectator['id'] = $id++;
            $players[] = $spectator;
        }

        return $players;
    }

    private function get_spectators($table) {
        $result = [];

        if ($table === null) {
            return [];
        }

        $trs = $table->getElementsByTagName('tr');

        $first = true;

        foreach($trs as $tr) {
            if ($first) {
                $first = false;
                continue;
            }

            $result[] = $this->get_spectator($tr);
        }

        return $result;
    }

    private function get_spectator($tr) {
        $td = $tr->getElementsByTagName('td');

        $player = $td->item(0);

        $spectates = $td->item(1);

        $flag = $this->xpath->query('.//img[@class="flag"]', $player)->item(0);

        $country = explode('.', basename($flag->getAttribute('src')))[0];

        if ($country === 'nocountry') {
            $country = '_404';
        }

        $name = $this->xpath->query('.//span[@class="visname"]', $player)->item(0);

        $spectates = $this->xpath->query('.//span[@class="visname"]', $spectates)->item(0);

        return [
            'name'          => $this->get_q3_string($name),
            'spectates'     => $this->get_q3_string($spectates),
            'country'       => strtoupper($country),
            'follow_num'    => -1
        ];
    }

    private function get_players($table) {
        $result = [];

        if ($table === null) {
            return [];
        }

        $trs = $table->getElementsByTagName('tr');

        $first = true;

        $id = 1;

        foreach($trs as $tr) {
            if ($first) {
                $first = false;
                continue;
            }

            $result[] = $this->get_player($tr, $id);
            $id++;
        }

        return $result;
    }

    private function get_player($tr, $id) {
        $td = $tr->getElementsByTagName('td');

        $player = $td->item(0);

        $time = $td->item(1)->textContent;

        $flag = $this->xpath->query('.//img[@class="flag"]', $player)->item(0);

        $country = explode('.', basename($flag->getAttribute('src')))[0];

        if ($country === 'nocountry') {
            $country = '_404';
        }

        $name = $this->xpath->query('.//span[@class="visname"]', $player)->item(0);

        return [
            'name'          => $this->get_q3_string($name),
            'time'          => $this->parse_time($time),
            'country'       => strtoupper($country),
            'follow_num'    => -1,
            'id'            => $id
        ];
    }

    private function parse_time($time) {
        $result = 0;

        $parts = explode(':', $time);

        if (count($parts) == 3) {
            list($minutes, $seconds, $millisecond) = array_map('intval', $parts);
            $result = ($minutes * 60000) + ($seconds * 1000) + $millisecond;
        } elseif (count($parts) == 2) {
            list($seconds, $millisecond) = array_map('intval', $parts);
            $result = ($seconds * 1000) + $millisecond;
        } else {
            return 0;
        }

        return $result;
    }

    private function get_q3_string($node) {
        $parts = $this->html_to_q3($node);

        $result = '';

        foreach($parts as $part) {
            $result .= $part['style'] . $part['text'];
        }

        return $result;
    }

    private function html_to_q3($node) {
        $result = [];

        foreach($node->childNodes as $child) {
            if ($child->nodeName === '#text') {
                $result[] = [
                    'style' =>  $this->get_q3_color($node->getAttribute('style')),
                    'text'  =>  $child->textContent,
                ];
            } else {
                $result = array_merge($result, $this->html_to_q3($child));
            }
        }

        return $result;
    }

    private function get_q3_color($style) {
        $colors = [
            'yellow'        =>      '^3',
            'red'           =>      '^1',
            '#B5B5B5'       =>      '^9',
            '#4D87AB'       =>      '^4',
            'cyan'          =>      '^5',
            'green'         =>      '^2',
            'purple'        =>      '^6',
            'white'         =>      '^7',
            'rgb(181, 181, 181)' => '^9'
        ];

        $parts = explode('color:', $style);

        $color = trim($parts[1]);

        if (! array_key_exists($color, $colors)) {
            return '^7';
        }

        return $colors[$color];
    }
}