<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applique la langue d'un groupe de routes publiques (/en, /mg, ou le français sans préfixe). */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        Locales::apply($locale);
        // Le paramètre {locale} n'existe que pour le routage : les contrôleurs n'ont pas à le recevoir.
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
