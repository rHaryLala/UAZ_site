{{-- Contenu de la page tel que l'éditeur le dessine : en-tête et blocs (mêmes composants que le site public). --}}
<div data-ve-hero>
    <x-page-hero :title="$title" :intro="$summary ?: null" :breadcrumbs="[]" :image="$page->cover" />
</div>

<x-blocks :blocks="$blocks" />
