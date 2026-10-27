<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Recherche plein texte simple (LIKE, compatible SQLite et MySQL) dans formations, actualités, événements et pages.
 * Partagée par la page /recherche (Livewire) et la fenêtre de recherche de l'en-tête.
 */
class SiteSearch
{
    public const MIN_LENGTH = 2;

    /**
     * @param  bool  $compact  moins de résultats par groupe (fenêtre de recherche)
     * @return Collection<string, Collection<int, array{title: string, url: string, excerpt: string, meta: ?string}>>
     */
    public function search(string $term, bool $compact = false): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $excerpt = fn (?string $text) => str(strip_tags((string) $text))->squish()->limit(160)->toString();
        $limit = fn (int $full) => $compact ? min($full, 4) : $full;

        return collect([
            'Formations' => Formation::visible()->with('faculty')
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('mention', 'like', $like)->orWhere('description', 'like', $like))
                ->limit($limit(8))->get()
                ->map(fn (Formation $f) => ['title' => $f->title, 'url' => $f->url(), 'excerpt' => $excerpt($f->summary), 'meta' => $f->level->getLabel()]),
            'Actualités' => NewsPost::visible()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like))
                ->orderByDesc('published_at')->limit($limit(8))->get()
                ->map(fn (NewsPost $p) => ['title' => $p->title, 'url' => $p->url(), 'excerpt' => $excerpt($p->excerpt ?: $p->body), 'meta' => $p->published_at?->translatedFormat('j F Y')]),
            'Événements' => Event::visible()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('description', 'like', $like))
                ->orderByDesc('starts_at')->limit($limit(5))->get()
                ->map(fn (Event $e) => ['title' => $e->title, 'url' => $e->url(), 'excerpt' => $excerpt($e->summary), 'meta' => $e->starts_at->translatedFormat('j F Y')]),
            'Pages' => Page::visible()->where('key', '!=', 'home')
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('blocks', 'like', $like))
                ->limit($limit(8))->get()
                ->map(fn (Page $p) => ['title' => $p->title, 'url' => $p->url(), 'excerpt' => $excerpt($p->summary), 'meta' => null]),
        ])->filter(fn ($items) => $items->isNotEmpty());
    }
}
