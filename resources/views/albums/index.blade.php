@php
    // Le mur du héros : les couvertures des albums, réparties en trois colonnes qui dérivent en sens contraires (décor, aucune légende).
    $covers = $albums->getCollection()->pluck('cover')->filter(fn ($cover) => $cover && $cover->file())->values();
    $columns = $covers->count() >= 3 ? $covers->groupBy(fn ($cover, $i) => $i % 3)->map(fn ($group) => $group->values()) : collect();

    // Rapport largeur / hauteur de chaque photo (borné pour garder un mur harmonieux).
    $ratio = function ($asset) {
        [$w, $h] = $asset->dimensions();

        return $w && $h ? round(max(0.8, min(1.6, $w / $h)), 3) : 1.333;
    };
@endphp

<x-layouts.app :title="__('Galerie photo')" :description="__('Albums photo de la vie à l\'Université Adventiste Zurcher.')">
    {{-- 1. LE VERNISSAGE : un mur de photos qui dérive, le titre se lève lettre à lettre (animations : resources/js/animations/gallery.js) --}}
    <header class="gal-hero on-dark" data-gallery data-gal-hero aria-labelledby="gal-title">
        @if ($columns->isNotEmpty())
            <div class="gal-hero__wall" aria-hidden="true">
                @foreach ($columns as $c => $items)
                    <div class="gal-hero__col" data-gal-col="{{ $c % 2 ? 'down' : 'up' }}">
                        {{-- La colonne est répétée pour que la dérive ne laisse jamais de vide. --}}
                        @foreach (range(1, 2) as $copy)
                            @foreach ($items as $cover)
                                <span class="gal-hero__photo"><x-picture :asset="$cover" conversion="card" sizes="30vw" decorative :eager="$c === 0 && $loop->first && $copy === 1" /></span>
                            @endforeach
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
        <div class="gal-hero__shade" aria-hidden="true"></div>

        <div class="container-site">
            <x-breadcrumbs :items="[['label' => __('Galerie')]]" dark />
        </div>

        <div class="container-site gal-hero__body">
            <p class="eyebrow mb-5 text-sun-400!" data-gal-hide data-gal-fade>{{ __('gallery.hero.eyebrow') }}</p>
            <h1 id="gal-title" class="gal-title" data-gal-title>{{ __('La vie à l\'UAZ en images') }}</h1>
            <p class="gal-lead" data-gal-hide data-gal-fade>{{ __('Albums photo de la vie à l\'Université Adventiste Zurcher.') }}</p>

            @if ($albums->total() > 0)
                <dl class="gal-stats" data-gal-hide data-gal-fade>
                    <div><dt class="sr-only">{{ __('gallery.stats.albums_label') }}</dt><dd><b data-gal-count="{{ $albums->total() }}">{{ $albums->total() }}</b> {{ trans_choice('gallery.stats.albums', $albums->total()) }}</dd></div>
                    @if ($photosTotal > 0)
                        <div><dt class="sr-only">{{ __('gallery.stats.photos_label') }}</dt><dd><b data-gal-count="{{ $photosTotal }}">{{ $photosTotal }}</b> {{ trans_choice('gallery.stats.photos', $photosTotal) }}</dd></div>
                    @endif
                </dl>
                <a href="#exposition" class="gal-cue" data-gal-hide data-gal-fade><span aria-hidden="true"></span>{{ __('Faites défiler') }}</a>
            @endif
        </div>
    </header>

    @if ($albums->isEmpty())
        <section class="gal-empty" aria-labelledby="gal-empty-title">
            <div class="container-site">
                <h2 id="gal-empty-title" class="gal-h2">{{ __('Aucun album pour le moment.') }}</h2>
                <p>{{ __('gallery.empty') }}</p>
            </div>
        </section>
    @else
        {{-- 2. L'EXPOSITION : les albums sont accrochés au mur, un projecteur suit le pointeur --}}
        <section id="exposition" class="gal-expo on-dark" data-gal-expo aria-labelledby="gal-expo-title">
            <div class="container-site">
                <div class="gal-expo__head">
                    <div>
                        <p class="eyebrow mb-3 text-sun-400!">{{ __('gallery.expo.eyebrow') }}</p>
                        <h2 id="gal-expo-title" class="gal-h2">{{ __('gallery.expo.title') }}</h2>
                    </div>

                    @if ($years->count() > 1)
                        <div class="gal-filter" data-gal-filter hidden role="group" aria-label="{{ __('gallery.filter.label') }}">
                            <button type="button" class="is-active" data-gal-year="all" aria-pressed="true">{{ __('gallery.filter.all') }}</button>
                            @foreach ($years as $year)
                                <button type="button" data-gal-year="{{ $year }}" aria-pressed="false">{{ $year }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <p class="sr-only" role="status" aria-live="polite" data-gal-status data-one="{{ trans_choice('gallery.filter.result', 1) }}" data-other="{{ trans_choice('gallery.filter.result', 2) }}"></p>

                <ul class="gal-wall" data-gal-wall>
                    @foreach ($albums as $album)
                        <li class="gal-frame" data-gal-frame data-slot="{{ $loop->index % 4 }}" data-year="{{ $album->taken_on?->year }}">
                            <article class="gal-frame__art" style="--ratio: {{ $album->cover ? $ratio($album->cover) : 1.333 }}">
                                <div class="gal-frame__mat" data-gal-mat>
                                    @if ($album->cover)
                                        <x-picture :asset="$album->cover" conversion="card" sizes="(min-width: 1024px) 40rem, (min-width: 640px) 55vw, 92vw" decorative class="gal-frame__img" data-gal-img />
                                    @else
                                        <span class="gal-frame__blank" aria-hidden="true"><x-madagascar class="h-[70%] text-white/20" /></span>
                                    @endif
                                </div>
                                <div class="gal-frame__label">
                                    <span class="gal-frame__no" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <div>
                                        <h3 class="gal-frame__title"><a href="{{ $album->url() }}" class="stretched-link" data-gal-link>{{ $album->title }}</a></h3>
                                        <p class="gal-frame__meta">{{ trans_choice(':count photo|:count photos', $album->photos_count) }}@if ($album->taken_on) · {{ $album->taken_on->translatedFormat('F Y') }}@endif</p>
                                    </div>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-16">{{ $albums->links() }}</div>
            </div>
            <span class="gal-cursor" aria-hidden="true" data-gal-cursor>{{ __('gallery.cursor.album') }}</span>
        </section>
    @endif

    {{-- 3. LA SUITE --}}
    <section class="gal-more on-dark" data-gal-more aria-labelledby="gal-more-title">
        <div class="container-site">
            <h2 id="gal-more-title" class="gal-more__text" data-gal-more-text>{{ __('gallery.more.text') }}</h2>
            <p class="mt-10 flex flex-wrap gap-4" data-reveal>
                <x-button :href="url('/actualites')" variant="sun" arrow class="btn-square">{{ __('gallery.more.news') }}</x-button>
                <x-button :href="url('/evenements')" variant="outline">{{ __('gallery.more.events') }}</x-button>
            </p>
        </div>
    </section>
</x-layouts.app>
