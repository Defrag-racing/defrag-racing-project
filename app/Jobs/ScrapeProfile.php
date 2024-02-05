<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DOMDocument;
use DOMXPath;

use App\Models\MddProfile;
use App\Models\User;

class ScrapeProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $mdd_id;

    public function __construct($mdd_id) {
        $this->mdd_id = $mdd_id;
    }

    public function handle(): void {
        list($name, $country) = $this->get_profile($this->mdd_id);

        $profile = MddProfile::where('id', $this->mdd_id)->first();

        if ($profile) {
            return;
        }

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


    public function get_profile ($id) {
        $url = 'https://q3df.org/profil?id=' . $id;

        $response = file_get_contents($url);

        if ($response === false) {
            return null;
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($response);

        libxml_clear_errors();

        $this->xpath = new DOMXPath($dom);

        $visname = $this->xpath->query('//div[contains(@class, "full-width")]')->item(0);

        $visname = $this->xpath->query('//span[contains(@class, "visname")]', $visname)->item(0);

        $flag = $this->xpath->query('//img[contains(@class, "flag")]')->item(0);
        
        //<img class="flag" src="/Views/frontend/_resources/images/flags/eg.gif">
        $country = $flag->getAttribute('src');
        $country = explode('/', $country);
        $country = explode('.', $country[count($country) - 1])[0];

        return [$this->get_q3_string($visname), strtoupper($country)];
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
