@php
    $cover = $post->cover && $post->cover->file() ? $post->cover : null;
    $link = $post->url();
@endphp

<x-layouts.app :title="$post->metaTitle()" :description="$post->metaDescription()" :image="$post->metaImage()" type="article" :noindex="! $post->isLive()">
    {{-- Barre de lecture : elle se remplit au fil de l'article --}}
    <div class="nws-progress" aria-hidden="true" data-nws-progress><i></i></div>

    {{-- 1. L'OUVERTURE : la photo respire, le titre se lève (animations : resources/js/animations/news.js) --}}
    <header @class(['nws-open', 'has-cover on-dark' => $cover]) data-news="article" data-nws-hero aria-labelledby="nws-title">
        @if ($cover)
            <div class="nws-open__cover" data-nws-cover>
                {{-- La photo principale de l'article garde le texte alternatif saisi par la rédaction. --}}
                <x-picture :asset="$cover" conversion="hero" sizes="100vw" eager class="size-full object-cover" />
            </div>
            <div class="nws-open__shade" aria-hidden="true"></div>
        @endif

        <div class="container-site">
            <x-breadcrumbs :items="[['label' => __('Actualités'), 'url' => url('/actualites')], ['label' => str($post->title)->limit(48)->toString()]]" :dark="(bool) $cover" />
        </div>

        <div class="container-site nws-open__body">
            <p class="nws-open__tag" data-nws-hide data-nws-fade>{{ $post->category?->name ?? __('Actualités') }}</p>
            <h1 id="nws-title" class="nws-open__title" data-nws-title>{{ $post->title }}</h1>
            @if ($post->excerpt)<p class="nws-open__lead" data-nws-hide data-nws-fade>{{ $post->excerpt }}</p>@endif
            <p class="nws-open__meta" data-nws-hide data-nws-fade>
                <span><x-ico name="calendar" class="size-4" /><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></span>
                @if ($post->author)<span>{{ __('Par :name', ['name' => $post->author->name]) }}</span>@endif
                <span><x-ico name="clock" class="size-4" />{{ __('news.article.reading', ['count' => $minutes]) }}</span>
            </p>
        </div>
    </header>

    {{-- 2. L'ARTICLE : colonne de lecture, lettrine, titres qui se soulignent, photos qui se dévoilent ; le partage reste à portée --}}
    <article class="nws-article" data-nws-article>
        <div class="container-site nws-article__layout">
            <div class="nws-prose" data-nws-prose>
                <x-rich :html="$post->body" />
                @if ($cover && ($cover->caption || $cover->credit))
                    <p class="nws-credit">{{ $cover->caption }} @if ($cover->credit)<span>© {{ $cover->credit }}</span>@endif</p>
                @endif
            </div>

            <aside class="nws-share" aria-label="{{ __('news.share.label') }}">
                <p class="nws-share__label" aria-hidden="true">{{ __('news.share.label') }}</p>
                <ul>
                    <li><a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($link) }}" target="_blank" rel="noopener noreferrer">Facebook<span class="sr-only"> ({{ __('timetables.new_tab') }})</span></a></li>
                    <li><a href="https://wa.me/?text={{ urlencode($post->title.' '.$link) }}" target="_blank" rel="noopener noreferrer">WhatsApp<span class="sr-only"> ({{ __('timetables.new_tab') }})</span></a></li>
                    <li><button type="button" hidden data-nws-copy="{{ $link }}">{{ __('news.share.copy') }}</button></li>
                </ul>
                <p class="sr-only" role="status" aria-live="polite" data-nws-copied></p>
            </aside>
        </div>

        <div class="container-site nws-article__end">
            <a href="{{ url('/actualites') }}" class="link-arrow"><x-ico name="arrow-left" class="size-4" /> {{ __('Toutes les actualités') }}</a>
        </div>
    </article>

    {{-- 3. D'UN ARTICLE À L'AUTRE --}}
    @if ($older || $newer)
        <nav class="nws-pager" aria-label="{{ __('news.article.pager') }}" data-nws-pager>
            @foreach ([['post' => $older, 'dir' => 'older', 'label' => __('news.article.older')], ['post' => $newer, 'dir' => 'newer', 'label' => __('news.article.newer')]] as $item)
                @if ($item['post'])
                    <a href="{{ $item['post']->url() }}" class="nws-pager__link is-{{ $item['dir'] }}" data-nws-pager-link>
                        @if ($item['post']->cover && $item['post']->cover->file())
                            <span class="nws-pager__img" aria-hidden="true"><x-picture :asset="$item['post']->cover" conversion="card" sizes="50vw" decorative class="size-full object-cover" /></span>
                        @endif
                        <span class="nws-pager__text">
                            <span class="nws-pager__dir">{{ $item['label'] }}</span>
                            <span class="nws-pager__title">{{ $item['post']->title }}</span>
                        </span>
                    </a>
                @endif
            @endforeach
        </nav>
    @endif

    @if ($related->isNotEmpty())
        <section class="nws-related" data-nws-related aria-labelledby="nws-related-title">
            <div class="container-site">
                <h2 id="nws-related-title" class="nws-h2">{{ __('À lire aussi') }}</h2>
                <ul class="nws-grid nws-grid--related">
                    @foreach ($related as $item)
                        <li class="nws-cell" data-nws-cell><x-news-tile :post="$item" /></li>
                    @endforeach
                </ul>
            </div>
            <span class="nws-cursor" aria-hidden="true" data-nws-cursor>{{ __('news.cursor') }}</span>
        </section>
    @endif

    @push('head')
        @include('partials.jsonld-article', ['post' => $post])
    @endpush
</x-layouts.app>
