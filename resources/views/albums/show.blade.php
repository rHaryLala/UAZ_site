<x-layouts.app :title="$album->title" :description="$album->description" :image="$album->cover" :noindex="! $album->isLive()">
    <x-page-hero eyebrow="Galerie" :title="$album->title" :intro="$album->description" :breadcrumbs="[['label' => 'Galerie', 'url' => url('/galerie')], ['label' => $album->title]]" />

    <x-section tone="paper" tight>
        <div x-data="lightbox">
            @if ($album->photos->isEmpty())
                <p class="text-ink-700">Cet album ne contient pas encore de photos.</p>
            @else
                @include('partials.gallery-grid', ['photos' => $album->photos->map(fn ($p) => ['asset' => $p, 'caption' => $p->caption]), 'dialogLabel' => $album->title])
            @endif
        </div>
        <p class="mt-12 border-t border-paper-300 pt-8"><a href="{{ url('/galerie') }}" class="link-arrow"><x-ico name="arrow-left" class="size-4" /> Tous les albums</a></p>
    </x-section>
</x-layouts.app>
