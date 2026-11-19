<?php

namespace App\Support;

/** Type d'appareil et navigateur d'après le User-Agent : seuls ces deux mots sont conservés, jamais le texte complet. */
class UserAgent
{
    /** @return array{device: string, browser: string} */
    public static function parse(?string $agent): array
    {
        $agent = (string) $agent;

        $device = match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $agent) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|windows phone|blackberry|opera mini/i', $agent) => 'mobile',
            default => 'desktop',
        };

        $browser = match (true) {
            (bool) preg_match('/edg(e|a|ios)?\//i', $agent) => 'Edge',
            (bool) preg_match('/opr\/|opera/i', $agent) => 'Opera',
            (bool) preg_match('/samsungbrowser/i', $agent) => 'Samsung Internet',
            (bool) preg_match('/firefox|fxios/i', $agent) => 'Firefox',
            (bool) preg_match('/msie|trident/i', $agent) => 'Internet Explorer',
            (bool) preg_match('/chrome|crios|chromium/i', $agent) => 'Chrome',
            (bool) preg_match('/safari/i', $agent) => 'Safari',
            default => 'Autre',
        };

        return ['device' => $device, 'browser' => $browser];
    }
}
