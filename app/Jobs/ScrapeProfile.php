<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\MddProfile;
use App\Models\User;

class ScrapeProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 3;
    public $backoff = [60, 300];

    private $mdd_id;

    public function __construct($mdd_id) {
        $this->mdd_id = $mdd_id;
    }

    public function handle(): void {
        $profile = MddProfile::where('id', $this->mdd_id)->first();

        if ($profile) {
            return;
        }

        try {
            $result = $this->get_profile($this->mdd_id);
        } catch (\Exception $e) {
            \Log::warning("ScrapeProfile failed for mdd_id={$this->mdd_id}: {$e->getMessage()}");
            throw $e; // retry instead of silently failing
        }

        if ($result === null) {
            return;
        }

        list($name, $country) = $result;

        $user = User::where('mdd_id', $this->mdd_id)->first();

        $pattern = '/\^\w/';
        $plainName = preg_replace($pattern, '', $name);

        $newProfile = new MddProfile();
        $newProfile->id = $this->mdd_id;
        $newProfile->name = $name;
        $newProfile->plain_name = $plainName;
        $newProfile->country = $country;

        if ($user) {
            $newProfile->user_id = $user->id;
        }

        $newProfile->processStats();

        $newProfile->save();
    }


    public function get_profile($id) {
        $url = 'https://q3df.org/api/getUser?' . http_build_query([
            'id' => $id,
        ]);

        $response = $this->fetch($url, true);
        if ($response === false) {
            $response = $this->fetch($url, false);
        }

        if ($response === false) {
            return null;
        }

        $user = json_decode($response, true);
        if (!is_array($user) || !isset($user['visname']) || !empty($user['deleted'])) {
            return null;
        }

        $country = $user['country'] ?? '_404';
        if ($country === '' || $country === 'nocountry') {
            $country = '_404';
        }

        return [$user['visname'], strtoupper($country)];
    }

    private function fetch(string $url, bool $verifySsl) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            return false;
        }

        return $response;
    }
}
