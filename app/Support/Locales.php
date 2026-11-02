<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Langues du site public. Le français n'a pas de préfixe d'URL ; les autres vivent sous /{code}.
 * Les liens du site sont écrits avec url() / route() : changer de langue revient à préfixer la racine des URL
 * (voir apply()), donc aucun lien n'a besoin de connaître la langue.
 */
final class Locales
{
    /** @return array<string, array{name: string, short: string, og: string}> */
    public static function all(): array
    {
        return config('uaz.locales');
    }

    /** @return list<string> Codes des langues autres que la langue par défaut (ceux qui ont un préfixe). */
    public static function prefixed(): array
    {
        return array_values(array_diff(array_keys(self::all()), [self::default()]));
    }

    public static function default(): string
    {
        return config('uaz.default_locale');
    }

    public static function current(): string
    {
        return app()->getLocale();
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? self::current()) === self::default();
    }

    /**
     * Traduit un texte dont on ne connaît pas la valeur à l'avance (libellé de menu, de fil d'Ariane, titre de rubrique).
     * Contrairement à __(), ne renvoie jamais un fichier de groupe (« About » ouvrirait lang/en/about.php sous Windows).
     */
    public static function tr(?string $text): string
    {
        $text = (string) $text;
        $translated = $text === '' ? $text : __($text);

        return is_string($translated) ? $translated : $text;
    }

    /** Heure affichée selon la langue : « 14h30 » en français, « 14:30 » ailleurs. */
    public static function time(\DateTimeInterface $date): string
    {
        return $date->format(self::isDefault() ? 'H\hi' : 'H:i');
    }

    /** Valeur de og:locale de la langue courante. */
    public static function og(): string
    {
        return self::all()[self::current()]['og'] ?? 'fr_FR';
    }

    /** Applique une langue : textes, dates et préfixe des URL générées (liens, routes, adresses signées). */
    public static function apply(string $locale, ?string $root = null): void
    {
        app()->setLocale($locale);
        \Illuminate\Support\Carbon::setLocale($locale);

        $root ??= request()->root();
        // Les fichiers (images, CSS, JS) ne sont jamais préfixés : on fixe leur origine avant de préfixer le reste.
        URL::useAssetOrigin($root);
        URL::forceRootUrl(self::isDefault($locale) ? null : $root.'/'.$locale);
    }

    /** Exécute un rappel avec les URL générées sans préfixe de langue (adresses techniques : mises à jour Livewire…). */
    public static function withoutPrefix(\Closure $callback): mixed
    {
        $forced = (new \ReflectionProperty(app('url'), 'forcedRoot'))->getValue(app('url'));
        URL::forceRootUrl(null);

        try {
            return $callback();
        } finally {
            URL::forceRootUrl($forced);
        }
    }

    /** Retour à l'état d'une requête neuve (langue par défaut, URL sans préfixe) : utile aux tests, qui enchaînent les requêtes. */
    public static function reset(): void
    {
        app()->setLocale(config('app.locale'));
        URL::forceRootUrl(null);
        URL::useAssetOrigin(null);
    }

    /** Le chemin de la requête sans le préfixe de langue (« actualites/x », « » pour l'accueil). */
    public static function barePath(?Request $request = null): string
    {
        $path = trim(($request ?? request())->path(), '/');

        foreach (self::prefixed() as $code) {
            if ($path === $code) {
                return '';
            }
            if (str_starts_with($path, $code.'/')) {
                return substr($path, strlen($code) + 1);
            }
        }

        return $path;
    }

    /** Une adresse absolue du site (écrite sans préfixe de langue) dans la langue demandée. */
    public static function localize(string $url, string $locale): string
    {
        $root = rtrim(request()->root(), '/');
        $path = str_starts_with($url, $root) ? substr($url, strlen($root)) : $url;
        $path = $path === '/' ? '' : $path;

        return $root.(self::isDefault($locale) ? '' : '/'.$locale).$path;
    }

    /** Adresse de la page courante dans une autre langue (la requête de recherche et les filtres sont conservés). */
    public static function urlFor(string $locale, ?Request $request = null): string
    {
        $request ??= request();
        $root = rtrim($request->root(), '/');
        $path = self::barePath($request);
        $prefix = self::isDefault($locale) ? '' : '/'.$locale;
        $query = $request->getQueryString();

        return $root.$prefix.($path !== '' ? '/'.$path : ($prefix === '' ? '/' : '')).($query ? '?'.$query : '');
    }
}
