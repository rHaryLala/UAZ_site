<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité du site public : CSP stricte à nonce, anti-clickjacking, MIME sniffing, referrer, permissions,
 * HSTS (production, HTTPS). Le back-office Filament n'est pas compatible avec une CSP stricte (scripts inline et
 * évaluation Alpine) : il ne reçoit que les en-têtes génériques.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $strict = ! $request->is('admin', 'admin/*') && ! Vite::isRunningHot();

        if ($strict) {
            // Le nonce doit exister avant le rendu des vues : Vite et Livewire l'ajoutent à leurs balises <script>/<style>.
            Vite::useCspNonce();
        }

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        if (app()->environment('production') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($strict && str_starts_with((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Content-Security-Policy', $this->policy(Vite::cspNonce(), app()->environment('production')));
        }

        return $response;
    }

    private function policy(string $nonce, bool $upgrade): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce'",
            // Feuilles de style : fichiers du site et balises <style> à nonce ; les attributs style="--i: 2" restent permis.
            "style-src 'self' 'nonce-$nonce'",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            // Carte OpenStreetMap de la page Contact (chargée seulement au clic de l'utilisateur).
            'frame-src https://www.openstreetmap.org',
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
        ];

        if ($upgrade) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
