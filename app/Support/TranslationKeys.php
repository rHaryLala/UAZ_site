<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Inventaire des textes traduisibles. Le français est la langue source : les vues écrivent __(« la phrase en français ») et
 * lang/{langue}.json donne la traduction (clé = la phrase française). Les textes éditoriaux longs vivent dans
 * lang/{langue}/{groupe}.php, avec la même structure dans toutes les langues.
 */
final class TranslationKeys
{
    /** Dossiers où chercher les appels __() / trans_choice() / @lang(). */
    private const SOURCES = ['app', 'resources/views', 'routes', 'config'];

    /** @return list<string> Phrases françaises utilisées comme clés (celles des groupes « home.hero.title » sont exclues). */
    public static function used(): array
    {
        $keys = [];

        foreach (self::SOURCES as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                // Les écrans du back-office restent en français.
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Filament'.DIRECTORY_SEPARATOR)
                    || str_contains($file->getPathname(), 'views'.DIRECTORY_SEPARATOR.'admin')
                    || str_contains($file->getPathname(), 'views'.DIRECTORY_SEPARATOR.'filament')) {
                    continue;
                }

                $code = file_get_contents($file->getPathname());
                preg_match_all('/(?:\b__|\btrans_choice|\btrans|@lang)\(\s*(\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")/s', $code, $m, PREG_SET_ORDER);

                foreach ($m as $hit) {
                    $key = $hit[2] !== '' ? stripcslashes(str_replace(["\\'"], ["'"], $hit[2])) : stripcslashes($hit[3] ?? '');
                    if ($key !== '' && ! self::isGroupKey($key)) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        foreach (JsStrings::KEYS as $text) {
            $keys[$text] = true;
        }

        // Données des pages Admissions et Frais (tableaux de config, traduits à l'affichage par Translated).
        foreach (['admissions', 'fees'] as $config) {
            foreach (Translated::strings(config($config)) as $text) {
                $keys[$text] = true;
            }
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return $keys;
    }

    /** « home.hero.title » est une clé de fichier de groupe ; « Accueil » ou « Une phrase. » sont des phrases. */
    public static function isGroupKey(string $key): bool
    {
        // Le point final accepté : préfixe d'une clé composée (« home.future.'.$key »).
        return (bool) preg_match('/^[a-z0-9_\-]+(\.[a-z0-9_\-]*)+$/', $key);
    }

    /** @return array<string, string> Dictionnaire d'une langue (lang/{langue}.json). */
    public static function dictionary(string $locale): array
    {
        $file = lang_path($locale.'.json');

        return is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    }

    /** @return list<string> Phrases utilisées dans le code mais absentes du dictionnaire. */
    public static function missing(string $locale): array
    {
        $dictionary = self::dictionary($locale);

        return array_values(array_filter(self::used(), fn (string $key) => ! array_key_exists($key, $dictionary) || trim((string) $dictionary[$key]) === ''));
    }

    /** @return list<string> Variables « :nom » d'une phrase. */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:[A-Za-z_]\w*/', $text, $m);
        $found = array_unique($m[0]);
        sort($found);

        return $found;
    }

    /** @return array<string, string> Clés « a.b.c » → texte d'un fichier de groupe, à plat. */
    public static function group(string $locale, string $group): array
    {
        $file = lang_path("$locale/$group.php");

        return is_file($file) ? \Illuminate\Support\Arr::dot(require $file) : [];
    }

    /** @return list<string> Groupes de textes éditoriaux (fichiers lang/fr/*.php sauf validation). */
    public static function groups(): array
    {
        return collect(File::files(lang_path('fr')))
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->reject(fn ($g) => $g === 'validation')
            ->values()->all();
    }
}
