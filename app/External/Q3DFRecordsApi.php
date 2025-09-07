<?php

namespace App\External;

use Carbon\Carbon;

class Q3DFRecordsApi {
    protected $url = "https://q3df.org/api/getLastRecords";

    public function getMddRecords() {
        $ch = curl_init($this->url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            return [];
        }

        return $this->mapRecords(json_decode($response, true));
    }

    private function mapRecords($records) {
        $mappedRecords = [];

        foreach($records['data'] as $record) {
            $mappedRecords[] = $this->getRecord($record);
        }

        return $mappedRecords;
    }

    private function getRecord($recordPart) {
        $date = Carbon::createFromTimestamp($recordPart['UnixTimestamp'], 'Europe/Berlin');
        $player = $this->get_player_from_mdduser($recordPart['User']);
        $time = $recordPart['MsTime'];
        $map = trim($recordPart['Map']);

        $physics = trim($recordPart['GameType']);
        $physicsParts = explode('-', $physics);

        $player['time'] = $time;
        $player['map'] = $map;
        $player['physics'] = $physicsParts[0];
        $player['mode'] = $physicsParts[1];
        $player['date'] = $date->toDateTimeString();

        return $player;
    }

    private function get_player_from_mdduser($mdduser) {
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
