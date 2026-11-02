@php
    // Pages « ressources » (maquette : Bibliothèque, Emplois du temps, Téléchargements, puis Système LMD) : celles qui existent et sont publiées.
    $resources = \App\Models\Page::visible()->with('cover.media')->whereIn('key', ['student-life-library', 'timetables', 'admissions-downloads', 'lmd'])->get()
        ->sortBy(fn ($p) => array_search($p->key, ['student-life-library', 'timetables', 'admissions-downloads', 'lmd']))->values();
@endphp

@if ($resources->isNotEmpty())
    {{-- Ressources : un sommaire. Chaque ligne se soulève au survol ; si la page a une image, elle apparaît et suit le pointeur (animations : home.js). --}}
    <section class="home-res" data-home="res" aria-labelledby="resources-title">
        <div class="container-site">
            <p class="eyebrow mb-4" data-reveal>{{ __('home.resources.eyebrow') }}</p>
            <h2 id="resources-title" class="home-res__title" data-reveal>{{ __('home.resources.title') }}</h2>

            <ol class="home-res__list" data-home-res>
                @foreach ($resources as $resource)
                    <li class="home-res__item" data-home-res-item>
                        <a href="{{ $resource->url() }}" class="home-res__link">
                            <span class="home-res__no" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="home-res__text">
                                <span class="eyebrow block">{{ __('Ressource') }}</span>
                                <span class="home-res__name">{{ $resource->title }}</span>
                                @if ($resource->summary)<span class="mt-1 block text-sm text-ink-700">{{ $resource->summary }}</span>@endif
                            </span>
                            <x-ico name="arrow-up-right" class="home-res__arrow size-7" />
                        </a>
                        @if ($resource->cover)
                            <span class="home-res__peek" aria-hidden="true" data-home-peek><x-picture :asset="$resource->cover" conversion="card" sizes="22rem" decorative /></span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
