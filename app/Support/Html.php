<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Assainit le HTML éditorial (éditeur riche, contenu importé) avant affichage : liste blanche stricte de balises,
 * schémas de liens limités, aucun script, iframe, style en ligne ni gestionnaire d'événement.
 * Les vidéos sont affichées par des blocs dédiés (jamais par du HTML libre).
 *
 * Les images importées ou insérées sont des <figure data-media="ID"> : après assainissement elles sont remplacées
 * par une image responsive (AVIF/WebP, dimensions explicites) issue de la médiathèque.
 */
class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): HtmlString
    {
        if (blank($html)) {
            return new HtmlString('');
        }

        return new HtmlString(self::sanitizer()->sanitize($html));
    }

    /** Assainit puis remplace les <figure data-media> par des images responsives de la médiathèque. */
    public static function render(?string $html): HtmlString
    {
        $clean = (string) self::clean($html);

        if (! str_contains($clean, 'data-media')) {
            return new HtmlString($clean);
        }

        $pattern = '#<figure data-media="(\d+)">(?:\s*<figcaption>(.*?)</figcaption>)?\s*</figure>#s';
        preg_match_all($pattern, $clean, $matches);
        $assets = MediaAsset::with('media')->whereIn('id', array_unique($matches[1]))->get()->keyBy('id');

        $clean = preg_replace_callback($pattern, function (array $m) use ($assets): string {
            $asset = $assets->get((int) $m[1]);

            if (! $asset || ! $asset->file()) {
                return ''; // image supprimée de la médiathèque
            }

            return view('partials.rich-image', ['asset' => $asset, 'caption' => $m[2] ?? null])->render();
        }, $clean);

        return new HtmlString($clean);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowElement('figure', ['data-media'])
                ->allowElement('figcaption')
                ->allowAttribute('loading', 'img')
                ->allowAttribute('width', 'img')
                ->allowAttribute('height', 'img')
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer'),
        );
    }
}
