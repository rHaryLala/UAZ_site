<?php

namespace App\Support;

/**
 * Traduction des tableaux de données rédigés en français (config/admissions.php, config/fees.php) : chaque texte passe par
 * le dictionnaire de la langue courante (lang/{langue}.json), sauf les valeurs qui servent d'identifiants ou de données
 * (dates, montants, coordonnées, clés de recherche dans la table des formations…).
 */
final class Translated
{
    /** Clés dont la valeur n'est jamais un texte à traduire. */
    public const KEEP = ['formation', 'date', 'phone', 'phones', 'email', 'value', 'group', 'amount', 'unit', 'address', 'year', 'url'];

    /** Même structure, textes traduits dans la langue courante. */
    public static function deep(mixed $data, ?string $key = null): mixed
    {
        if (is_array($data)) {
            return collect($data)->map(fn ($value, $k) => self::deep($value, is_string($k) ? $k : $key))->all();
        }

        return is_string($data) && ! in_array($key, self::KEEP, true) && self::isText($data) ? __($data) : $data;
    }

    /** @return list<string> Les textes à traduire d'un tableau de données (pour l'inventaire des traductions). */
    public static function strings(array $data, ?string $key = null): array
    {
        $found = [];

        foreach ($data as $k => $value) {
            $k = is_string($k) ? $k : $key;
            if (is_array($value)) {
                array_push($found, ...self::strings($value, $k));
            } elseif (is_string($value) && ! in_array($k, self::KEEP, true) && self::isText($value)) {
                $found[] = $value;
            }
        }

        return array_values(array_unique($found));
    }

    /** Un texte lisible (et non un nombre, une heure, une date, une adresse e-mail ou un identifiant). */
    private static function isText(string $value): bool
    {
        return (bool) preg_match('/[A-Za-zÀ-ÿ]{2}/u', $value)
            && ! preg_match('/^[\w.+-]+@[\w.-]+$/', $value)
            && ! preg_match('/^[\d\s\-:\/.,%+hH]+$/u', $value);
    }
}
