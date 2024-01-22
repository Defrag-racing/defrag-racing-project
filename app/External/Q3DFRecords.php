<?php

namespace App\External;

use \DOMDocument;
use \DOMXPath;
use Carbon\Carbon;

class Q3DFRecords {
    protected $url = "https://q3df.org/records?page=";
    protected $xpath;

    public function scrape_until($start, $record) {
        $result = [];

        $page = $start;
        $found = false;

        while (! $found) {
            $records = $this->scrape($page);
            $page++;

            $found = $this->check_dates($record, $records) !== -1;

            usleep(500000); // 0.5 second

            $result = array_merge($result, $records);
        }

        return $result;
    }

    public function scrape_through($start, $pages) {
        $result = [];

        for($i = $start; $i <= $start + $pages; $i++) {
            $records = $this->scrape($i);
            $result = array_merge($result, $records);

            usleep(500000); // 0.5 second
        }

        return $result;
    }

    public function scrape($page) {
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

        $recordsTable = $this->xpath->query('//table[contains(@class, "recordlist")]/tbody')->item(0);

        return $this->getRecords($recordsTable);
    }

    private function check_dates($record, $records) {
        foreach($records as $index => $searchRecord) {
            $searchRecordDate = Carbon::parse($searchRecord['date']);
            $recordDate = Carbon::parse($record->date_set);

            $interval = $searchRecordDate->diff($recordDate);

            if ($interval->days >= 1 && $interval->invert == 0) {
                return $index;
            }
        }

        return -1;
    }

    private function getRecords($recordsTable) {
        $records = [];

        $recordsParts = $recordsTable->getElementsByTagName('tr');

        foreach($recordsParts as $recordPart) {
            $records[] = $this->getRecord($recordPart);
        }

        return $records;
    }

    private function getRecord($recordPart) {
        $parts = $recordPart->getElementsByTagName('td');

        $date = $this->parse_date($parts->item(0)->textContent);

        $player = $this->get_player($parts->item(1));

        $time = $this->parse_time($parts->item(2)->textContent);

        $map = trim($parts->item(3)->textContent);

        $physics = $parts->item(5)->textContent;
        $physicsParts = explode('-', $physics);

        $player['time'] = $time;
        $player['map'] = $map;

        $player['physics'] = $physicsParts[0];
        $player['mode'] = $physicsParts[1];

        $player['date'] = $date->toDateTimeString();

        return $player;
    }

    private function parse_date($date) {
        $cleanedDate = str_replace(['th', 'st', 'nd', 'rd'], '', $date);

        $carbonDate = Carbon::createFromFormat('M d, \'y, H:i', $cleanedDate);

        return $carbonDate;
    }

    private function get_player($part) {
        $flag = $this->xpath->query('.//img[@class="flag"]', $part)->item(0);

        $country = explode('.', basename($flag->getAttribute('src')))[0];

        if ($country === 'nocountry') {
            $country = '_404';
        }

        $name = $this->xpath->query('.//span[@class="visname"]', $part)->item(0);

        $a = $this->xpath->query('.//a[@class="userlink"]', $part)->item(0);
        $mdd_id = explode('?id=', basename($a->getAttribute('href')))[1];

        return [
            'name'      =>  $this->get_q3_string($name),
            'country'   =>  strtoupper($country),
            'mdd_id'    =>  intval($mdd_id)
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