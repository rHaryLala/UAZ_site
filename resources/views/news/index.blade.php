@php
    // « À la une » : la publication la plus récente de la sélection, sur la première page seulement ; les autres vont dans le fil.
    $lead = $posts->onFirstPage() ? $posts->first() : null;
    $rest = $lead ? $posts->getCollection()->slice(1)->values() : $posts->getCollection()->values();
    $level = $lead ? 'h3' : 'h2';

    $pill = fn (bool $on) => 'nws-tab'.($on ? ' is-active' : '');
@endphp

<x-layouts.app :title="$category ? __('Actualités').' — '.$category->name : __('Actualités')" :description="__('Les dernières nouvelles de l\'Université Adventiste Zurcher : vie du campus, formations, événements.')">
    {{-- 1. LA UNE : un titre de journal, la date de la dernière publication (animations : resources/js/animations/news.js) --}}
    <header class="nws-hero" data-news data-nws-hero aria-labelledby="nws-title">
        <div class="container-site">
            <x-breadcrumbs :items="[['label' => __('Actualités')]]" />
        </div>

        <div class="container-site nws-hero__body">
            <p class="nws-masthead" data-nws-hide data-nws-fade>
                <span>{{ __('Les dernières nouvelles de l\'UAZ') }}</span>
                @if ($latestDate)<span aria-hidden="true" class="nws-masthead__rule"></span><span>{{ __('news.hero.latest', ['date' => \Illuminate\Support\Carbon::parse($latestDate)->translatedFormat('j F Y')]) }}</span>@endif
            </p>
            <h1 id="nws-title" class="nws-title" data-nws-title>{{ __('Actualités de l\'UAZ') }}</h1>
        </div>
    </header>

    {{-- 2. LE RUBAN : les derniers titres défilent, plus vite quand on fait défiler la page --}}
    @if ($headlines->count() >= 3)
        <div class="nws-ticker" aria-hidden="true" data-nws-ticker>
            <div class="nws-ticker__track" data-nws-ticker-track>
                @foreach (range(1, 2) as $copy)
                    <p class="nws-ticker__run">
                        @foreach ($headlines as $headline)
                            <span>{{ $headline->title }}</span><i></i>
                        @endforeach
                    </p>
                @endforeach
            </div>
        </div>
    @endif

    {{-- 3. LE FIL : l'article à la une, puis les autres actualités accrochées à des formats variés --}}
    <section class="nws-wall" data-nws-wall aria-label="{{ __('Actualités') }}">
        <div class="container-site">
            <div data-ajax-region>
                @if ($categories->count() > 1)
                    <nav aria-label="{{ __('Catégories d\'actualités') }}" class="nws-tabs">
                        <a href="{{ url('/actualites') }}" class="{{ $pill(! $category) }}" @if (! $category) aria-current="true" @endif>{{ __('Toutes') }}</a>
                        @foreach ($categories as $item)
                            <a href="{{ url('/actualites?categorie='.$item->slug) }}" class="{{ $pill((bool) $category?->is($item)) }}" @if ($category?->is($item)) aria-current="true" @endif>{{ $item->name }}</a>
                        @endforeach
                    </nav>
                @endif

                @if ($posts->isEmpty())
                    <p class="nws-empty">{{ __('Aucune actualité pour le moment.') }}</p>
                @else
                    @if ($lead)
                        <article class="nws-lead" data-nws-lead data-nws-card>
                            <div class="nws-lead__media" data-nws-media>
                                @if ($lead->cover && $lead->cover->file())
                                    <x-picture :asset="$lead->cover" conversion="hero" sizes="(min-width: 1024px) 56rem, 100vw" decorative eager class="nws-lead__img" data-nws-img />
                                @else
                                    <span class="nws-card__blank" aria-hidden="true"><x-madagascar class="h-3/4 text-white/15" /></span>
                                @endif
                            </div>
                            <div class="nws-lead__body">
                                <p class="nws-lead__flag">{{ __('news.lead.flag') }}</p>
                                <p class="nws-card__meta">
                                    @if ($lead->category)<span class="nws-card__cat">{{ $lead->category->name }}</span>@endif
                                    <time datetime="{{ $lead->published_at?->toDateString() }}">{{ $lead->published_at?->translatedFormat('j F Y') }}</time>
                                </p>
                                <h2 class="nws-lead__title"><a href="{{ $lead->url() }}" class="stretched-link" data-nws-link><span class="nws-mark">{{ $lead->title }}</span></a></h2>
                                @if ($lead->excerpt)<p class="nws-lead__excerpt">{{ $lead->excerpt }}</p>@endif
                                <span class="nws-card__more" aria-hidden="true">{{ __('Lire l\'article') }} <x-ico name="arrow-right" class="size-4" /></span>
                            </div>
                        </article>
                    @endif

                    @if ($rest->isNotEmpty())
                        <ul class="nws-grid" data-nws-grid>
                            @foreach ($rest as $post)
                                <li class="nws-cell" data-nws-cell data-slot="{{ $loop->index % 5 }}"><x-news-tile :post="$post" :level="$level" /></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="nws-pages">{{ $posts->links() }}</div>
                @endif
            </div>
        </div>
        <span class="nws-cursor" aria-hidden="true" data-nws-cursor>{{ __('news.cursor') }}</span>
    </section>

    {{-- 4. LA SUITE --}}
    <section class="nws-more on-dark" data-nws-more aria-labelledby="nws-more-title">
        <div class="container-site">
            <h2 id="nws-more-title" class="nws-more__text" data-nws-more-text>{{ __('news.more.text') }}</h2>
            <p class="mt-10 flex flex-wrap gap-4" data-reveal>
                <button type="button" data-newsletter-open aria-haspopup="dialog" class="btn btn-sun btn-square">{{ __('news.more.subscribe') }}</button>
                <x-button :href="url('/evenements')" variant="outline">{{ __('news.more.events') }}</x-button>
            </p>
        </div>
    </section>
</x-layouts.app>
