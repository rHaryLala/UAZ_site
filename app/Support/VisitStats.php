<?php

namespace App\Support;

use App\Models\SiteVisit;
use Illuminate\Support\Facades\Cache;

/**
 * Chiffres de fréquentation du tableau de bord, calculés à partir des visites enregistrées par App\Http\Middleware\TrackVisit.
 *
 * Un « visiteur » est unique par jour (l'empreinte change chaque jour, voir TrackVisit) : sur plusieurs jours, on additionne les visiteurs
 * de chaque jour. Une même personne qui revient trois jours de suite compte donc trois fois ; c'est le prix de l'absence de cookie.
 * Les résultats sont gardés 5 minutes en cache : le tableau de bord ne relance pas ces requêtes à chaque affichage.
 */
class VisitStats
{
    private const TTL = 300;

    /**
     * Visiteurs et pages vues de chaque jour, du plus ancien au plus récent, aujourd'hui compris (0 les jours sans visite).
     *
     * @return array<string, array{visitors: int, views: int}>
     */
    public static function daily(int $days): array
    {
        return Cache::remember("visits.daily.{$days}", self::TTL, function () use ($days) {
            $from = now()->subDays($days - 1)->toDateString();

            $rows = SiteVisit::query()
                ->where('visited_on', '>=', $from)
                ->selectRaw('visited_on, COUNT(DISTINCT visitor) as visitors, COUNT(*) as views')
                ->groupBy('visited_on')
                ->get()
                ->mapWithKeys(fn ($row) => [substr((string) $row->visited_on, 0, 10) => ['visitors' => (int) $row->visitors, 'views' => (int) $row->views]]);

            $series = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $day = now()->subDays($i)->toDateString();
                $series[$day] = $rows[$day] ?? ['visitors' => 0, 'views' => 0];
            }

            return $series;
        });
    }

    /** @return array{today: int, yesterday: int, week: int, month: int, views_month: int, since: ?string} */
    public static function summary(): array
    {
        $daily = self::daily(30);
        $visitors = array_column($daily, 'visitors');

        return [
            'today' => (int) end($visitors),
            'yesterday' => (int) ($visitors[count($visitors) - 2] ?? 0),
            'week' => array_sum(array_slice($visitors, -7)),
            'month' => array_sum($visitors),
            'views_month' => array_sum(array_column($daily, 'views')),
            'since' => self::since(),
        ];
    }

    /** Date de la première visite comptée (le comptage commence au déploiement : il n'y a pas d'historique avant). */
    public static function since(): ?string
    {
        return Cache::remember('visits.since', self::TTL, fn () => SiteVisit::query()->min('visited_on') ? substr((string) SiteVisit::query()->min('visited_on'), 0, 10) : null);
    }

    /** @return list<array{path: string, views: int, visitors: int}> */
    public static function topPages(int $days = 30, int $limit = 8): array
    {
        return Cache::remember("visits.top.{$days}.{$limit}", self::TTL, fn () => SiteVisit::query()
            ->where('visited_on', '>=', now()->subDays($days - 1)->toDateString())
            ->selectRaw('path, COUNT(*) as views, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('path')
            ->orderByDesc('views')
            ->orderBy('path')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['path' => $row->path, 'views' => (int) $row->views, 'visitors' => (int) $row->visitors])
            ->all());
    }

    /**
     * Les villes d'où viennent les visiteurs (d'après l'adresse IP : approximatif), avec leur pays ; les visites sans lieu sont ignorées.
     *
     * @return list<array{place: string, visitors: int}>
     */
    public static function topPlaces(int $days = 30, int $limit = 6): array
    {
        return Cache::remember("visits.places.{$days}.{$limit}", self::TTL, fn () => SiteVisit::query()
            ->where('visited_on', '>=', now()->subDays($days - 1)->toDateString())
            ->whereNotNull('country_code')
            ->selectRaw('country_code, country, city, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('country_code', 'country', 'city')
            ->orderByDesc('visitors')
            ->orderBy('city')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['place' => $row->city ? $row->city.', '.$row->country : (string) $row->country, 'visitors' => (int) $row->visitors])
            ->all());
    }

    /** Les sites d'où viennent les visiteurs (liens, réseaux sociaux, moteurs de recherche), sans les visites directes. @return list<array{host: string, views: int}> */
    public static function topReferrers(int $days = 30, int $limit = 5): array
    {
        return Cache::remember("visits.referrers.{$days}.{$limit}", self::TTL, fn () => SiteVisit::query()
            ->where('visited_on', '>=', now()->subDays($days - 1)->toDateString())
            ->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as views')
            ->groupBy('referrer')
            ->orderByDesc('views')
            ->orderBy('referrer')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['host' => $row->referrer, 'views' => (int) $row->views])
            ->all());
    }
}
