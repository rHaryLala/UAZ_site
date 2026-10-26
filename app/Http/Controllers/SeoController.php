<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\PhotoAlbum;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml (généré automatiquement, mis en cache 1 h) et robots.txt (fermé hors production). */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $entries = collect([
                ['url' => url('/'), 'priority' => '1.0', 'freq' => 'weekly'],
                ['url' => url('/formations'), 'priority' => '0.9', 'freq' => 'weekly'],
                ['url' => url('/actualites'), 'priority' => '0.8', 'freq' => 'daily'],
                ['url' => url('/evenements'), 'priority' => '0.7', 'freq' => 'weekly'],
                ['url' => url('/galerie'), 'priority' => '0.5', 'freq' => 'monthly'],
                ['url' => url('/equipe'), 'priority' => '0.5', 'freq' => 'monthly'],
                ['url' => url('/contact'), 'priority' => '0.6', 'freq' => 'yearly'],
            ]);

            $models = [
                [Page::published()->where('key', '!=', 'home')->get(), '0.7', 'monthly'],
                [Formation::published()->get(), '0.8', 'monthly'],
                [NewsPost::published()->get(), '0.6', 'never'],
                [Event::published()->get(), '0.5', 'weekly'],
                [PhotoAlbum::published()->get(), '0.4', 'never'],
            ];

            foreach ($models as [$items, $priority, $freq]) {
                foreach ($items as $item) {
                    $entries->push([
                        'url' => $item->url(),
                        'lastmod' => $item->updated_at?->toIso8601String(),
                        'priority' => $priority,
                        'freq' => $freq,
                    ]);
                }
            }

            return view('seo.sitemap', ['entries' => $entries])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->environment('production')
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /livewire', 'Disallow: /recherche', 'Allow: /', '', 'Sitemap: '.url('/sitemap.xml')]
            // Hors production (préproduction, développement) : aucune indexation.
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
