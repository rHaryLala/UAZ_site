<?php

namespace App\Livewire;

use App\Services\SiteSearch;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Page de recherche : la logique est dans App\Services\SiteSearch (partagée avec la fenêtre de recherche de l'en-tête). */
class Search extends Component
{
    #[Url(as: 'q', history: true)]
    public string $query = '';

    /** @return Collection<string, Collection<int, array{title: string, url: string, excerpt: string, meta: ?string}>> */
    public function getResultsProperty(): Collection
    {
        return app(SiteSearch::class)->search($this->query);
    }

    public function render(): View
    {
        return view('livewire.search', ['results' => $this->results, 'term' => trim($this->query)]);
    }
}
