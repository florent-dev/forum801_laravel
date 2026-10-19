<?php

namespace App\Services;

use Illuminate\Support\Str;

// Self-hosted image captcha: no third-party service, codes live in the session
class CaptchaService
{
    // No look-alike characters (0/O, 1/I/l)
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected const LENGTH = 5;

    protected const TTL = 600;

    public function enabled(): bool
    {
        return (bool) \App\Support\ForumSettings::get('captcha');
    }

    // New code for this session; each image replaces the previous code
    public function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        session(['captcha' => ['hash' => hash('sha256', $code), 'expires' => time() + self::TTL]]);

        return $code;
    }

    // One attempt per code: it is forgotten whatever the result
    public function check(?string $input): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        $stored = session()->pull('captcha');
        if (! $stored || $stored['expires'] < time() || ! $input) {
            return false;
        }

        return hash_equals($stored['hash'], hash('sha256', Str::upper(preg_replace('/\s+/', '', $input))));
    }

    public function png(string $code): string
    {
        $w = 180;
        $h = 56;
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 14, 36, 45));
        // Bold static font: readable once distorted (DejaVu Sans, free license)
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');

        // Background noise
        for ($i = 0; $i < 7; $i++) {
            $c = imagecolorallocatealpha($img, random_int(40, 120), random_int(90, 160), random_int(100, 170), 60);
            imagesetthickness($img, random_int(1, 3));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        for ($i = 0; $i < 160; $i++) {
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), imagecolorallocate($img, random_int(60, 140), random_int(100, 170), random_int(110, 180)));
        }

        // Characters with random angle, size, color and vertical offset
        $palette = [[0, 190, 190], [242, 193, 78], [46, 207, 115], [237, 103, 234], [120, 180, 255], [235, 120, 120]];
        $x = 14;
        foreach (str_split($code) as $char) {
            [$r, $g, $b] = $palette[array_rand($palette)];
            $size = random_int(20, 24);
            imagettftext($img, $size, random_int(-25, 25), $x, random_int(36, 46), imagecolorallocate($img, $r, $g, $b), $font, $char);
            $x += random_int(28, 33);
        }

        // A couple of strike-through curves on top of the text
        for ($i = 0; $i < 2; $i++) {
            $c = imagecolorallocatealpha($img, 201, 201, 201, 70);
            imagesetthickness($img, 2);
            $y = random_int(15, $h - 15);
            $amp = random_int(4, 9);
            $prev = null;
            for ($px = 0; $px < $w; $px += 4) {
                $py = (int) ($y + $amp * sin($px / random_int(18, 22)));
                if ($prev) {
                    imageline($img, $prev[0], $prev[1], $px, $py, $c);
                }
                $prev = [$px, $py];
            }
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
