<?php

namespace App\External;

use Carbon\Carbon;

class Q3DFRecordsApi {
    public $url = "https://q3df.org/api/getRecords";

    public $timeout = 3;

    public function getMddRecords($page = 0, $per_page = 100, $api_key = null) {
        $params = [
            'page' => intval($page),
            'per_page' => intval($per_page),
        ];

        if ($api_key) {
            $params['key'] = $api_key;
        }

        $ch = curl_init($this->url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        curl_close($ch);

        // null is a failed request. An empty data list is a real last page.
        if ($response === false || $response_status !== 200) {
            return null;
        }

        $json = json_decode($response, true);
        if (!$json || !isset($json['data']) || !is_array($json['data'])) {
            return null;
        }

        return [$info['starttransfer_time'], $json['count'] ?? 0, $this->mapRecords($json)];
    }

    protected function mapRecords($records) {
        $mappedRecords = [];

        foreach($records['data'] as $record) {
            $mappedRecords[] = $this->getRecord($record);
        }

        return $mappedRecords;
    }

    protected function getRecord($recordPart) {
        $date = Carbon::createFromTimestamp($recordPart['UnixTimestamp'], 'Europe/Berlin');
        $player = $this->get_player_from_mdduser($recordPart['User']);
        $time = $recordPart['MsTime'];
        $map = trim($recordPart['Map']);

        $physics = trim($recordPart['GameType']);
        $physicsParts = explode('-', $physics);

        $player['time'] = $time;
        $player['map'] = $map;
        $player['physics'] = $physicsParts[0];
        $player['mode'] = $physicsParts[1] ?? '';
        $player['date'] = $date->toDateTimeString();

        return $player;
    }

    protected function get_player_from_mdduser($mdduser) {
        $country = $mdduser['Country'] ?? '_404';

        if ($country === 'nocountry' || !$country) {
            $country = '_404';
        }

        return [
            'name'      =>  $mdduser['Visname'],
            'country'   =>  strtoupper($country),
            'mdd_id'    =>  $mdduser['Id']
        ];
    }
}
