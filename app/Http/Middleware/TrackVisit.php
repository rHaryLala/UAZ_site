<?php

namespace App\Http\Middleware;

use App\Models\SiteVisit;
use App\Services\IpGeolocation;
use App\Support\UserAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Compte les visites du site public pour le tableau de bord, sans cookie et sans conserver d'adresse IP.
 *
 * Le visiteur est une empreinte (HMAC) de son adresse IP et de son navigateur, calculée avec une clé qui change chaque jour : elle ne
 * permet ni de retrouver l'adresse, ni de suivre une personne d'un jour à l'autre. Un visiteur est donc « unique par jour », et ses pages
 * du jour se regroupent en un parcours. Le lieu (pays, région, ville) est déduit de l'adresse IP avec une base locale (IpGeolocation) ;
 * seul ce lieu approximatif est gardé, avec l'appareil et le navigateur (un mot chacun), jamais l'adresse ni le texte du navigateur.
 * Rien n'est enregistré pour l'administration, les robots, les fichiers, les erreurs, les personnes connectées au back-office,
 * ni pour les adresses qui portent une signature (désinscription de la newsletter) ; la partie « ?… » de l'adresse n'est jamais conservée.
 * L'enregistrement se fait après l'envoi de la réponse (terminate) : la visite n'attend pas la base de données.
 */
class TrackVisit
{
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|preview|monitor|uptime|pingdom|lighthouse|gtmetrix|headless|curl|wget|python|java\/|go-http|httpclient|okhttp|axios|node-fetch|libwww|scrapy|semrush|ahrefs|mj12|dotbot|whatsapp|telegram|discord|slack|linkedinbot|pinterest|embedly|validator|zabbix|nagios|statuscake/i';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $this->shouldTrack($request, $response)) {
                return;
            }

            $today = now()->toDateString();
            $place = app(IpGeolocation::class)->lookup($request->ip());
            $client = UserAgent::parse($request->userAgent());

            SiteVisit::query()->insert([
                'visited_on' => $today,
                'visitor' => $this->visitor($request, $today),
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'locale' => app()->getLocale(),
                'referrer' => $this->externalReferrer($request),
                'country_code' => $place['country_code'] ?? null,
                'country' => $place ? mb_substr((string) $place['country'], 0, 80) : null,
                'region' => $place ? mb_substr((string) $place['region'], 0, 100) ?: null : null,
                'city' => $place ? mb_substr((string) $place['city'], 0, 100) ?: null : null,
                'device' => $client['device'],
                'browser' => $client['browser'],
                'visited_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Une statistique manquée ne doit jamais gêner le site.
            report($e);
        }
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! config('uaz.analytics.enabled') || ! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        if (! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        if ($request->headers->get('Purpose') === 'prefetch' || str_starts_with((string) $request->headers->get('Sec-Purpose'), 'prefetch')) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');

        if ($request->is('admin', 'admin/*', 'livewire/*', 'filament/*', 'storage/*', 'build/*', 'up', 'sitemap.xml', 'robots.txt')
            || str_contains($path, '/newsletter/') || $request->query->has('signature')) {
            return false;
        }

        $agent = (string) $request->userAgent();

        return $agent !== '' && ! preg_match(self::BOTS, $agent) && $request->user() === null;
    }

    private function visitor(Request $request, string $day): string
    {
        $dailyKey = hash_hmac('sha256', $day, (string) config('app.key'));

        return substr(hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), $dailyKey), 0, 32);
    }

    /** Le site d'où vient la personne (nom de domaine seulement), ou null si c'est ce site lui-même ou si elle n'a rien indiqué. */
    private function externalReferrer(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('Referer'), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./i', '', $host));
        $own = strtolower(preg_replace('/^www\./i', '', $request->getHost()));

        return $host === $own ? null : mb_substr($host, 0, 100);
    }
}
