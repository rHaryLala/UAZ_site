@php
    // « À la une » (la plus récente ou la mise en avant) en grand, les deux suivantes à côté : mêmes vignettes que la page Actualités.
    $lead = $news->first();
    $rest = $news->slice(1)->values();
@endphp

<section class="home-news" data-home="news" data-news="home" aria-labelledby="home-news-title">
    <div class="container-site">
        <header class="home-news__head">
            <div>
                <p class="eyebrow mb-4" data-reveal>{{ __('home.news.eyebrow') }}</p>
                <h2 id="home-news-title" class="home-news__title" data-reveal>{{ __('home.news.title') }}</h2>
            </div>
            <x-button :href="url('/actualites')" variant="outline" arrow data-reveal data-home-magnet>{{ __('home.news.link') }}</x-button>
        </header>

        <div class="home-news__grid">
            <article class="nws-lead home-news__lead" data-nws-card>
                <div class="nws-lead__media" data-nws-media>
                    @if ($lead->cover && $lead->cover->file())
                        <x-picture :asset="$lead->cover" conversion="hero" sizes="(min-width: 1024px) 56vw, 100vw" decorative class="nws-lead__img" data-nws-img />
                    @else
                        <span class="nws-card__blank" aria-hidden="true"><x-madagascar class="h-3/4 text-white/15" /></span>
                    @endif
                </div>
                <div class="nws-lead__body">
                    <p class="nws-card__meta">
                        @if ($lead->category)<span class="nws-card__cat">{{ $lead->category->name }}</span>@endif
                        <time datetime="{{ $lead->published_at?->toDateString() }}">{{ $lead->published_at?->translatedFormat('j F Y') }}</time>
                    </p>
                    <h3 class="nws-lead__title"><a href="{{ $lead->url() }}" class="stretched-link" data-nws-link><span class="nws-mark">{{ $lead->title }}</span></a></h3>
                    @if ($lead->excerpt)<p class="nws-lead__excerpt">{{ $lead->excerpt }}</p>@endif
                    <span class="nws-card__more" aria-hidden="true">{{ __('Lire l\'article') }} <x-ico name="arrow-right" class="size-4" /></span>
                </div>
            </article>

            @if ($rest->isNotEmpty())
                <ul class="home-news__side">
                    @foreach ($rest as $post)
                        <li><x-news-tile :post="$post" /></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <span class="nws-cursor" aria-hidden="true" data-nws-cursor>{{ __('news.cursor') }}</span>
</section>
