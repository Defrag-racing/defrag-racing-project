<?php

namespace App\External;

use \DOMDocument;
use \DOMXPath;
use Carbon\Carbon;

class WorldSpawn {
    protected $url = "https://ws.q3df.org/maps/?show=50&page=";
    protected $mapUrl = "https://ws.q3df.org/map/";
    protected $xpath;

    public function scrape ($page) {
        $page = $page - 1;
        echo 'Scraping page: ' . $page . PHP_EOL;

        $response = file_get_contents($this->url . $page);

        if ($response === false) {
            return [];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($response);

        libxml_clear_errors();

        $this->xpath = new DOMXPath($dom);

        $mapsTable = $this->xpath->query("//table[@id='maps_table']")->item(0);

        return $this->getMaps($mapsTable);
    }

    public function scrape_until ($mapname) {
        $page = 0;
        $maps = [];

        while (1) {
            echo 'Scraping page: ' . $page . PHP_EOL;

            $response = file_get_contents($this->url . $page);

            if ($response === false) {
                return [];
            }

            libxml_use_internal_errors(true);

            $dom = new DOMDocument();
            $dom->loadHTML($response);

            libxml_clear_errors();

            $this->xpath = new DOMXPath($dom);

            $mapsTable = $this->xpath->query("//table[@id='maps_table']")->item(0);

            $result = $this->getMaps($mapsTable, $mapname);

            $maps = array_merge($maps, $result['maps']);

            if ($result['found']) {
                break;
            }

            $page++;

            if ($page > 10) {
                break;
            }
        }

        dd($this->getMapsData($maps));
        return $this->getMapsData($maps);
    }

    public function getMaps($table, $searchmap=NULL) {
        $rows = $table->getElementsByTagName('tr');
        $first = true;

        $maps = [];
        $found = false;

        foreach($rows as $row) {
            if ($first) {
                $first = false;
                continue;
            }

            $parts = $row->getElementsByTagName('td');

            $map = $parts->item(1);

            $a = $map->getElementsByTagName('a')->item(0);

            $url = $a->getAttribute('href');

            $mapname = trim(str_replace('/map/', '', $url));
            $mapname = str_replace('/', '', $mapname);

            if ($searchmap !== NULL && $searchmap == $mapname) {
                $found = true;
                break;
            }

            $maps[] = $mapname;
        }

        return [
            'found' =>  $found,
            'maps'  =>  $maps
        ];
    }

    public function getMapsData($maps) {
        $result = [];

        foreach($maps as $map) {
            $result[] = $this->getMap($map);
        }

        return $result;
    }

    public function getMap($map) {
        $response = file_get_contents($this->mapUrl . $map);

        if ($response === false) {
            return [];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($response);

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $mapsTable = $xpath->query("//table[@id='maps_table']")->item(0);
    }
}