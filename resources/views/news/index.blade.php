@php
    $pill = fn (bool $on) => 'inline-flex min-h-11 items-center rounded-full border px-5 text-sm font-semibold transition '.($on
        ? 'border-navy-800 bg-navy-800 text-white'
        : 'border-ink-300 bg-paper-50 text-ink-800 hover:border-navy-800');
@endphp

<x-layouts.app :title="$category ? 'Actualités — '.$category->name : 'Actualités'" description="Les dernières nouvelles de l'Université Adventiste Zurcher : vie du campus, formations, événements.">
    <x-page-hero eyebrow="Les dernières nouvelles de l'UAZ" title="Actualités de l'UAZ" :breadcrumbs="[['label' => 'Actualités']]" />

    <x-section tone="paper" tight>
        @if ($categories->count() > 1)
            <nav aria-label="Catégories d'actualités" class="mb-10 flex flex-wrap items-center gap-3">
                <a href="{{ url('/actualites') }}" class="{{ $pill(! $category) }}" @if (! $category) aria-current="true" @endif>Toutes</a>
                @foreach ($categories as $item)
                    <a href="{{ url('/actualites?categorie='.$item->slug) }}" class="{{ $pill((bool) $category?->is($item)) }}" @if ($category?->is($item)) aria-current="true" @endif>{{ $item->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($posts->isEmpty())
            <p class="rounded-card bg-paper-50 p-8 text-ink-700 shadow-card">Aucune actualité pour le moment.</p>
        @else
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <li data-reveal data-reveal-delay="{{ ($loop->index % 3) * 0.08 }}"><x-news-card :post="$post" level="h2" :eager="$loop->first" /></li>
                @endforeach
            </ul>
            {{ $posts->links() }}
        @endif
    </x-section>
</x-layouts.app>
