@props(['post', 'level' => 'h3', 'eager' => false])

{{--
    Vignette éditoriale d'une actualité (liste, « à lire aussi »). La mise en page (grande ou petite) vient de data-slot, voir resources/css/news.css.
    Tout est tiré de l'article : image, rubrique, date, titre, chapeau.
--}}
@php
    $asset = $post->cover;
@endphp

<article {{ $attributes->class('nws-card') }} data-nws-card>
    <div class="nws-card__media" data-nws-media>
        {{-- L'image mène à l'article comme le titre (lien doublon : hors ordre de tabulation et caché aux lecteurs d'écran). --}}
        <a href="{{ $post->url() }}" class="nws-media-link" tabindex="-1" aria-hidden="true">
            @if ($asset && $asset->file())
                <x-picture :asset="$asset" conversion="card" sizes="(min-width: 1024px) 40rem, (min-width: 640px) 50vw, 92vw" decorative :eager="$eager" class="nws-card__img" data-nws-img />
            @else
                <span class="nws-card__blank" aria-hidden="true"><x-madagascar class="h-3/4 text-white/15" /></span>
            @endif
        </a>
    </div>

    <div class="nws-card__body">
        <p class="nws-card__meta">
            @if ($post->category)<span class="nws-card__cat">{{ $post->category->name }}</span>@endif
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time>
        </p>
        <{{ $level }} class="nws-card__title"><a href="{{ $post->url() }}" class="stretched-link" data-nws-link><span class="nws-mark">{{ $post->title }}</span></a></{{ $level }}>
        @if ($post->excerpt)<p class="nws-card__excerpt">{{ $post->excerpt }}</p>@endif
        <span class="nws-card__more" aria-hidden="true">{{ __('Lire l\'article') }} <x-ico name="arrow-right" class="size-4" /></span>
    </div>
</article>
