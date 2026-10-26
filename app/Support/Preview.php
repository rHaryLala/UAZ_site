<?php

namespace App\Support;

/**
 * Mode aperçu : un compte connecté au back-office (permission « content.view ») voit aussi les brouillons
 * et contenus programmés en ajoutant ?apercu=1 à l'adresse publique (bouton « Aperçu » du back-office).
 */
class Preview
{
    public static function active(): bool
    {
        $request = request();

        return $request->boolean('apercu') && (bool) $request->user()?->can('content.view');
    }
}
