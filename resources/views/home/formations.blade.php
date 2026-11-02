@if ($domains->isNotEmpty())
    {{-- Les domaines de formation : des panneaux qui s'ouvrent au survol ou au clavier ; le panneau ouvert montre ses mentions (animations : home.js). --}}
    <section id="formations" class="home-dom on-dark" data-home="dom" aria-labelledby="formations-title">
        <div class="container-site">
            <header class="home-dom__head">
                <div>
                    <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ __('home.formations.eyebrow') }}</p>
                    <h2 id="formations-title" class="home-dom__title" data-reveal>{{ __('home.formations.title') }}</h2>
                </div>
                <a href="{{ url('/formations') }}" class="link-arrow text-white!" data-reveal>{{ __('home.formations.link') }} <x-ico name="arrow-right" class="size-4" /></a>
            </header>

            <ul class="home-dom__row" data-home-dom>
                @foreach ($domains as $domain)
                    <li class="home-panel" data-home-panel style="--tone: {{ $loop->index % 4 }}">
                        @if ($domain->cover)
                            <x-picture :asset="$domain->cover" conversion="card" sizes="(min-width: 1024px) 40vw, 92vw" decorative class="home-panel__img" />
                        @else
                            <x-madagascar class="home-panel__map" />
                        @endif
                        <div class="home-panel__body">
                            <span class="home-panel__no" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="home-panel__name">{{ $domain->name }}</h3>
                            <div class="home-panel__more">
                                <p class="eyebrow mb-3 text-sun-400!">{{ $domain->type->getLabel() }}</p>
                                <ul>
                                    @foreach ($domain->formations as $formation)
                                        <li><a href="{{ $formation->url() }}"><x-ico name="chevron-right" class="size-3.5" />{{ $formation->mention ?: $formation->title }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="home-dom__levels" data-reveal>
                <p>{{ __('home.formations.paths') }}</p>
                @foreach ($levels as $item)
                    <a href="{{ url('/formations?niveau='.$item->level->value) }}">
                        {{ $item->level->getLabel() }}@if ($item->duration) · {{ $item->duration }}@endif @if ($item->credits) · {{ __(':credits crédits ECTS', ['credits' => $item->credits]) }} @endif
                    </a>
                @endforeach
                <span>{{ __('home.formations.continuing') }}</span>
            </div>
        </div>
    </section>
@endif
