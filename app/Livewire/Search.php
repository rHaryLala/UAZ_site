<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Recherche plein texte simple (LIKE, compatible SQLite et MySQL) dans formations, actualités, événements et pages. */
class Search extends Component
{
    #[Url(as: 'q', history: true)]
    public string $query = '';

    private const MIN_LENGTH = 2;

    /** @return Collection<string, Collection<int, array{title: string, url: string, excerpt: string, meta: ?string}>> */
    public function getResultsProperty(): Collection
    {
        $term = trim($this->query);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $excerpt = fn (?string $text) => str(strip_tags((string) $text))->squish()->limit(160)->toString();

        return collect([
            'Formations' => Formation::visible()->with('faculty')
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('mention', 'like', $like)->orWhere('description', 'like', $like))
                ->limit(8)->get()
                ->map(fn (Formation $f) => ['title' => $f->title, 'url' => $f->url(), 'excerpt' => $excerpt($f->summary), 'meta' => $f->level->getLabel()]),
            'Actualités' => NewsPost::visible()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like))
                ->orderByDesc('published_at')->limit(8)->get()
                ->map(fn (NewsPost $p) => ['title' => $p->title, 'url' => $p->url(), 'excerpt' => $excerpt($p->excerpt ?: $p->body), 'meta' => $p->published_at?->translatedFormat('j F Y')]),
            'Événements' => Event::visible()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('description', 'like', $like))
                ->orderByDesc('starts_at')->limit(5)->get()
                ->map(fn (Event $e) => ['title' => $e->title, 'url' => $e->url(), 'excerpt' => $excerpt($e->summary), 'meta' => $e->starts_at->translatedFormat('j F Y')]),
            'Pages' => Page::visible()->where('key', '!=', 'home')
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('blocks', 'like', $like))
                ->limit(8)->get()
                ->map(fn (Page $p) => ['title' => $p->title, 'url' => $p->url(), 'excerpt' => $excerpt($p->summary), 'meta' => null]),
        ])->filter(fn ($items) => $items->isNotEmpty());
    }

    public function render(): View
    {
        return view('livewire.search', ['results' => $this->results, 'term' => trim($this->query)]);
    }
}
