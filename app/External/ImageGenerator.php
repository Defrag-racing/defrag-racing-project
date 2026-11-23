<?php

namespace App\External;

use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class ImageGenerator {
    public function generate ($id) {
        $data = $this->split_integer($id);

        try {
            $img = imagecreatetruecolor(128, 128);
            $blackColor = imagecolorallocate($img, 0, 0, 0);
    
            imagefill($img, 0, 0, $blackColor);

            $image = Storage::disk('assets')->path('images/connect.png');
            $imgBase = imagecreatefrompng($image);
    
            imagecopy($img, $imgBase, 0, 0, 0, 0, 128, 128);
    
            $length = count($data);

            foreach($data as $index => $digit) {
                $color = imagecolorallocate($img, 255, $digit, 0);
                imagesetpixel($img, $index, 0, $color);
            }

            # ending pixel
            $color = imagecolorallocate($img, 0, 0, 0);
            imagesetpixel($img, $length, 0, $color);

            for($i = $length + 1; $i <= 128; $i++) {
                $color = imagecolorallocate($img, 255, $data[$i % $length], 0);
                imagesetpixel($img, $i, 0, $color);
            }
    
            ob_start();
            imagepng($img);
            $imageData = ob_get_clean();
    
            imagedestroy($img);
            imagedestroy($imgBase);
    
            $base64Image = base64_encode($imageData);
    
            return 'data:image/png;base64,' . $base64Image;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function verify ($id) {
        $imageUrl = $this->get_mdd_image($id);

        if ($imageUrl === null) {
            return false;
        }

        // Download image via Guzzle (bypass SSL verification for q3df.org)
        $imageData = $this->fetchUrl($imageUrl);
        if ($imageData === null) {
            return false;
        }

        $image = imagecreatefromstring($imageData);

        $width = imagesx($image);
        $height = imagesy($image);

        $digits = [];

        for ($x = 0; $x < $width; $x++) {
            $colorIndex = imagecolorat($image, $x, 0);

            $rgb = imagecolorsforindex($image, $colorIndex);

            $red = $rgb['red'];
            $green = $rgb['green'];
            $blue = $rgb['blue'];

            if ($red == 0 && $green == 0 && $blue == 0) {
                break;
            }

            $digits[] = $green;
        }

        imagedestroy($image);

        $string = (string)implode('', $digits);

        return ($string === (string)$id);
    }

    public function get_mdd_image($id) {
        $profile = $this->get_profile($id);
        if ($profile === null || empty($profile['avatar'])) {
            return null;
        }

        return $this->absoluteUrl($profile['avatar']);
    }

    private function fetchUrl($url) {
        try {
            $client = new Client();
            $response = $client->get($url, ['verify' => false]);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function split_integer($number) {
        $numberString = (string)$number;

        $digitsArray = str_split($numberString);

        $digitsArray = array_map('intval', $digitsArray);

        return $digitsArray;
    }


    public function get_name ($id) {
        $profile = $this->get_profile($id);
        if ($profile === null || !isset($profile['visname']) || $profile['visname'] === '') {
            return null;
        }

        return $profile['visname'];
    }

    private function get_profile($id) {
        $response = $this->fetchUrl('https://q3df.org/api/getUser?' . http_build_query([
            'id' => $id,
        ]));

        if ($response === null) {
            return null;
        }

        $profile = json_decode($response, true);
        if (!is_array($profile) || !isset($profile['visname'])) {
            return null;
        }

        return $profile;
    }

    private function absoluteUrl($path) {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return 'https://q3df.org' . $path;
    }
}