<?php

namespace App\External;

class Q3DFServers {
    protected $url = "https://q3df.org/api/getServerList";

    public function scrape() {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            return [];
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            return [];
        }

        $servers = [];
        foreach ($payload['data'] as $server) {
            if (!is_array($server) || empty($server['address'])) {
                continue;
            }
            $servers[$server['address']] = $server;
        }

        return $servers;
    }
}
