@php
    // Rapport largeur / hauteur de chaque photo (borné pour garder un mur harmonieux).
    $ratio = function ($asset) {
        [$w, $h] = $asset->dimensions();

        return $w && $h ? round(max(0.8, min(1.6, $w / $h)), 3) : 1.333;
    };
    $cover = $album->cover ?? $album->photos->first();
    $count = $album->photos->count();
@endphp

<x-layouts.app :title="$album->title" :description="$album->description" :image="$album->cover" :noindex="! $album->isLive()">
    {{-- 1. L'OUVERTURE : la couverture respire, le titre se lève (animations : resources/js/animations/gallery.js) --}}
    <header class="gal-hero gal-hero--album on-dark" data-gallery data-gal-hero aria-labelledby="gal-title">
        @if ($cover && $cover->file())
            <div class="gal-hero__cover" data-gal-cover aria-hidden="true">
                <x-picture :asset="$cover" conversion="hero" sizes="100vw" decorative eager class="size-full object-cover" />
            </div>
        @endif
        <div class="gal-hero__shade" aria-hidden="true"></div>

        <div class="container-site">
            <x-breadcrumbs :items="[['label' => __('Galerie'), 'url' => url('/galerie')], ['label' => $album->title]]" dark />
        </div>

        <div class="container-site gal-hero__body">
            <p class="eyebrow mb-5 text-sun-400!" data-gal-hide data-gal-fade>{{ __('gallery.album.eyebrow') }}</p>
            <h1 id="gal-title" class="gal-title" data-gal-title>{{ $album->title }}</h1>
            @if ($album->description)<p class="gal-lead" data-gal-hide data-gal-fade>{{ $album->description }}</p>@endif
            <p class="gal-stats" data-gal-hide data-gal-fade>
                <span><b>{{ $count }}</b> {{ trans_choice('gallery.stats.photos', $count) }}</span>
                @if ($album->taken_on)<span>{{ $album->taken_on->translatedFormat('F Y') }}</span>@endif
            </p>
        </div>
    </header>

    {{-- 2. LE MUR : chaque photo est accrochée à son rythme ; un clic l'agrandit dans la visionneuse --}}
    <section class="gal-expo gal-expo--album on-dark" data-gal-expo aria-label="{{ $album->title }}">
        <div class="container-site" x-data="lightbox">
            @if ($album->photos->isEmpty())
                <p class="gal-empty-text">{{ __('Cet album ne contient pas encore de photos.') }}</p>
            @else
                <ul class="gal-wall gal-wall--photos" data-gal-wall>
                    @foreach ($album->photos as $photo)
                        <li class="gal-frame" data-gal-frame data-slot="{{ $loop->index % 7 }}">
                            <figure class="gal-frame__art" style="--ratio: {{ $ratio($photo) }}">
                                <a href="{{ $photo->url('hero') }}" data-lightbox-item data-alt="{{ $photo->alt }}" data-caption="{{ $photo->caption }}" data-gal-link class="gal-frame__mat" data-gal-mat aria-label="{{ __('gallery.album.open', ['number' => $loop->iteration]) }}{{ $photo->alt ? ' — '.$photo->alt : '' }}">
                                    <x-picture :asset="$photo" conversion="card" sizes="(min-width: 1024px) 40rem, (min-width: 640px) 50vw, 92vw" decorative class="gal-frame__img" data-gal-img />
                                </a>
                                @if ($photo->caption)<figcaption class="gal-frame__caption">{{ $photo->caption }}</figcaption>@endif
                            </figure>
                        </li>
                    @endforeach
                </ul>
                @include('partials.gallery-dialog', ['dialogLabel' => $album->title])
            @endif
        </div>
        <span class="gal-cursor" aria-hidden="true" data-gal-cursor>{{ __('gallery.cursor.photo') }}</span>
    </section>

    {{-- 3. LA SUITE --}}
    <section class="gal-more on-dark" data-gal-more aria-labelledby="gal-more-title">
        <div class="container-site">
            <h2 id="gal-more-title" class="gal-more__text" data-gal-more-text>{{ __('gallery.more.text') }}</h2>
            <p class="mt-10 flex flex-wrap gap-4" data-reveal>
                <x-button :href="url('/galerie')" variant="sun" class="btn-square">{{ __('gallery.album.back') }}</x-button>
                <x-button :href="url('/actualites')" variant="outline">{{ __('gallery.more.news') }}</x-button>
            </p>
        </div>
    </section>
</x-layouts.app>
