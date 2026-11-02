@php($items = $studentLife)

@if ($items->isNotEmpty())
    {{-- La vie à l'UAZ : les lieux s'empilent, chacun recouvre le précédent en le reculant (animations : home.js). --}}
    <section class="home-life" data-home="life" aria-labelledby="life-title">
        <div class="container-site home-life__head">
            <p class="eyebrow mb-4" data-reveal>{{ __('home.life.eyebrow') }}</p>
            <h2 id="life-title" class="home-life__title" data-reveal>{{ __('home.life.title') }}</h2>
            <p class="mt-5 max-w-2xl text-lead text-ink-700" data-reveal data-reveal-delay="0.1">{{ __('home.life.text') }}</p>
            <a href="{{ url('/vie-etudiante/bibliotheque') }}" class="link-arrow mt-6" data-reveal>{{ __('home.life.link') }} <x-ico name="arrow-right" class="size-4" /></a>
        </div>

        <ol class="home-stack" data-home-stack>
            @foreach ($items as $item)
                @php($page = $item['page'])
                <li class="home-card on-dark" data-home-card>
                    <div class="home-card__inner" data-home-card-inner>
                        @if ($item['photo'])
                            <x-picture :asset="$item['photo']" conversion="hero" sizes="100vw" decorative class="home-card__img" data-home-card-img />
                        @endif
                        <div class="home-card__shade" aria-hidden="true"></div>
                        <div class="container-site home-card__body">
                            <span class="home-card__no" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="home-card__title"><a href="{{ $page->url() }}" class="stretched-link">{{ $page->title }}</a></h3>
                            @if ($page->summary)<p class="home-card__text">{{ $page->summary }}</p>@endif
                            <span class="home-card__more" aria-hidden="true">{{ __('En savoir plus') }} <x-ico name="arrow-up-right" class="size-5" /></span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
