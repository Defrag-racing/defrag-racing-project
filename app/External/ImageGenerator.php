<?php

namespace App\External;

use Illuminate\Support\Facades\Storage;
use DOMDocument;
use DOMXPath;

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
        $imagePath = $this->get_mdd_image($id);

        $image = imagecreatefrompng($imagePath);

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

        $image = $this->xpath->query('//img[contains(@class, "profil_avatar")]')->item(0);

        return 'https://q3df.org' . $image->getAttribute('src');
    }

    private function split_integer($number) {
        $numberString = (string)$number;

        $digitsArray = str_split($numberString);

        $digitsArray = array_map('intval', $digitsArray);

        return $digitsArray;
    }


    public function get_name ($id) {
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

        return $this->get_q3_string($visname);
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