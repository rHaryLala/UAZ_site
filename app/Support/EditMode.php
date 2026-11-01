<?php

namespace App\Support;

/**
 * Mode « édition » du rendu public : activé uniquement par l'éditeur visuel (App\Http\Controllers\Admin\VisualEditorController).
 * Les vues de blocs y ajoutent des repères (enveloppe de bloc, images du texte riche) ; le site public n'en contient jamais.
 */
final class EditMode
{
    private static bool $on = false;

    public static function on(): bool
    {
        return self::$on;
    }

    public static function enable(): void
    {
        self::$on = true;
    }

    public static function disable(): void
    {
        self::$on = false;
    }
}
