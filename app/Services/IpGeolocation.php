<?php

namespace App\Services;

use App\Support\MaxMindDb\Reader;
use Throwable;

/**
 * Situe un visiteur (pays, région, ville) d'après son adresse IP, avec la base gratuite « DB-IP City Lite » lue en local
 * (storage/app/geoip/dbip-city-lite.mmdb, mise à jour chaque mois par `php artisan geoip:update`).
 *
 * L'adresse n'est envoyée à aucun service extérieur et n'est jamais conservée : seul le résultat l'est. C'est une APPROXIMATION : une adresse IP
 * ne donne pas l'emplacement exact d'une personne, seulement celui de son fournisseur d'accès ou de son quartier, au mieux la ville. En 4G / 5G, la
 * ville affichée est souvent celle où le réseau de l'opérateur sort sur Internet. Sans base (pas encore téléchargée) ou pour une adresse
 * privée ou inconnue, le résultat est null et la visite est comptée sans lieu.
 *
 * Données : DB-IP.com (licence Creative Commons Attribution 4.0) : l'attribution figure sur la page « Confidentialité et cookies ».
 */
class IpGeolocation
{
    private static ?Reader $reader = null;

    private static ?string $readerPath = null;

    public static function path(): string
    {
        return storage_path('app/geoip/dbip-city-lite.mmdb');
    }

    public static function available(): bool
    {
        return is_file(self::path());
    }

    /** @return array{country_code: ?string, country: ?string, region: ?string, city: ?string}|null */
    public function lookup(?string $ip): ?array
    {
        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false || ! self::available()) {
            return null;
        }

        try {
            $record = $this->reader()->get($ip);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($record) || empty($record['country']['iso_code'])) {
            return null;
        }

        $name = fn (?array $entry): ?string => isset($entry['names']) ? ($entry['names']['fr'] ?? $entry['names']['en'] ?? null) : null;

        return [
            'country_code' => $record['country']['iso_code'],
            'country' => $name($record['country']),
            'region' => $name($record['subdivisions'][0] ?? null),
            'city' => $name($record['city'] ?? null),
        ];
    }

    /** Libère la base ouverte (après une mise à jour du fichier, et dans les tests). */
    public static function reset(): void
    {
        self::$reader?->close();
        self::$reader = null;
        self::$readerPath = null;
    }

    private function reader(): Reader
    {
        if (self::$reader === null || self::$readerPath !== self::path()) {
            self::reset();
            self::$reader = new Reader(self::path());
            self::$readerPath = self::path();
        }

        return self::$reader;
    }
}
