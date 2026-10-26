<x-layouts.app title="Galerie photo" description="Albums photo de la vie à l'Université Adventiste Zurcher.">
    <x-page-hero eyebrow="Galerie" title="La vie à l'UAZ en images" :breadcrumbs="[['label' => 'Galerie']]" />

    <x-section tone="paper" tight>
        @if ($albums->isEmpty())
            <p class="rounded-card bg-paper-50 p-8 text-ink-700 shadow-card">Aucun album pour le moment.</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $album)
                    <li data-reveal data-reveal-delay="{{ ($loop->index % 3) * 0.08 }}">
                        <article class="card group h-full">
                            <div class="relative aspect-[4/3] overflow-hidden bg-navy-800">
                                @if ($album->cover)
                                    <x-picture :asset="$album->cover" conversion="card" sizes="(min-width: 1024px) 28rem, (min-width: 640px) 45vw, 92vw" class="size-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                @else
                                    <x-madagascar class="absolute inset-y-6 right-6 h-[85%] text-navy-700" />
                                @endif
                            </div>
                            <div class="p-6">
                                <h2 class="text-h4 font-serif"><a href="{{ $album->url() }}" class="stretched-link hover:text-navy-600">{{ $album->title }}</a></h2>
                                <p class="mt-2 text-sm text-ink-700">{{ $album->photos_count }} photo{{ $album->photos_count > 1 ? 's' : '' }}@if ($album->taken_on) · {{ $album->taken_on->translatedFormat('F Y') }}@endif</p>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
            {{ $albums->links() }}
        @endif
    </x-section>
</x-layouts.app>
