<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Météo du campus (Open-Meteo : gratuit, sans clé, sans cookie). L'appel est fait par le serveur, jamais par le navigateur du visiteur
 * (pas de fuite d'adresse IP, pas d'impact sur la politique de sécurité). Résultat en cache 10 minutes ; au-delà il est servi
 * pendant qu'il se rafraîchit en arrière-plan. Toute panne renvoie null : le pied de page n'affiche alors simplement rien.
 */
class CampusWeather
{
    /** @return array{temperature: int, label: string, icon: string}|null */
    public function current(): ?array
    {
        if (! config('uaz.weather.enabled')) {
            return null;
        }

        return Cache::flexible('campus-weather', [600, 3600], fn () => $this->fetch());
    }

    /** @return array{temperature: int, label: string, icon: string}|null */
    private function fetch(): ?array
    {
        try {
            $response = Http::timeout(3)->retry(1, 200, throw: false)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => config('uaz.weather.latitude'),
                'longitude' => config('uaz.weather.longitude'),
                'current' => 'temperature_2m,weather_code,is_day',
                'timezone' => 'auto',
            ]);

            $current = $response->successful() ? $response->json('current') : null;
        } catch (Throwable) {
            return null;
        }

        if (! is_array($current) || ! isset($current['temperature_2m'], $current['weather_code'])) {
            return null;
        }

        [$label, $icon] = $this->describe((int) $current['weather_code'], (bool) ($current['is_day'] ?? true));

        return ['temperature' => (int) round($current['temperature_2m']), 'label' => $label, 'icon' => $icon];
    }

    /**
     * Codes météo WMO → libellé français et icône.
     *
     * @return array{0: string, 1: string}
     */
    public function describe(int $code, bool $day = true): array
    {
        return match (true) {
            $code === 0 => $day ? ['Ciel dégagé', 'sun'] : ['Ciel dégagé', 'moon'],
            $code === 1 => $day ? ['Plutôt dégagé', 'sun'] : ['Plutôt dégagé', 'moon'],
            $code === 2 => ['Partiellement nuageux', $day ? 'cloud-sun' : 'cloud'],
            $code === 3 => ['Couvert', 'cloud'],
            in_array($code, [45, 48], true) => ['Brouillard', 'fog'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['Bruine', 'rain'],
            in_array($code, [61, 63, 65, 66, 67], true) => ['Pluie', 'rain'],
            in_array($code, [80, 81, 82], true) => ['Averses', 'rain'],
            in_array($code, [95, 96, 99], true) => ['Orage', 'storm'],
            default => ['Variable', 'cloud'],
        };
    }
}
