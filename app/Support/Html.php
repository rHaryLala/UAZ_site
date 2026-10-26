<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Assainit le HTML éditorial (éditeur riche, contenu importé) avant affichage : liste blanche stricte de balises,
 * schémas de liens limités, aucun script, iframe, style en ligne ni gestionnaire d'événement.
 * Les vidéos sont affichées par des blocs dédiés (jamais par du HTML libre).
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

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowElement('figure')
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
