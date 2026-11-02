@php
    $contact = url('/contact?sujet='.rawurlencode(__('Emplois du temps')));
    $count = $sheets->count();

    // Le tableau du héros : cinq colonnes, neuf lignes, des blocs [colonne, ligne, durée, ton]. Décor pur : il ne représente aucun horaire réel.
    $board = [
        [1, 1, 2, 'a'], [1, 4, 3, 'c'], [1, 8, 2, 'b'],
        [2, 2, 3, 'b'], [2, 6, 2, 'd'], [2, 9, 1, 'a'],
        [3, 1, 1, 'd'], [3, 3, 2, 'a'], [3, 6, 3, 'c'],
        [4, 2, 2, 'c'], [4, 5, 2, 'a'], [4, 8, 2, 'b'],
        [5, 1, 3, 'e'], [5, 5, 2, 'd'], [5, 8, 2, 'a'],
    ];
    $tones = ['soft', 'mid', 'strong'];
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. LA SEMAINE : un tableau d'emploi du temps se compose bloc après bloc, une ligne de temps le parcourt (animations : resources/js/animations/timetables.js) --}}
    <header class="tt-hero" data-timetables data-tt-hero aria-labelledby="tt-title">
        <div class="container-site">
            <x-breadcrumbs :items="$trail" />
        </div>

        <div class="container-site tt-hero__inner">
            <div class="tt-hero__text">
                <p class="eyebrow mb-5" data-tt-hide data-tt-fade>{{ __('timetables.hero.eyebrow') }}</p>
                <h1 id="tt-title" class="tt-title" data-tt-title>{{ $page->title }}</h1>
                <p class="tt-lead" data-tt-hide data-tt-fade>{{ $page->summary ?: __('timetables.hero.lead') }}</p>
                @if ($count)
                    <p class="mt-9" data-tt-hide data-tt-fade><x-button href="#mentions" variant="royal" arrow class="btn-square">{{ __('timetables.hero.cta') }}</x-button></p>
                @endif
            </div>

            <div class="tt-stage" aria-hidden="true">
                <div class="tt-board" data-tt-board>
                    <div class="tt-board__cols">@foreach (range(1, 5) as $i)<i data-tt-col></i>@endforeach</div>
                    <div class="tt-board__blocks">
                        @foreach ($board as [$c, $r, $s, $tone])
                            <i class="tt-block is-{{ $tone }}" style="--c: {{ $c }}; --r: {{ $r }}; --s: {{ $s }}" data-tt-block></i>
                        @endforeach
                    </div>
                    <span class="tt-now" data-tt-now><b></b></span>
                </div>
            </div>
        </div>
    </header>

    @if ($count)
        {{-- 2. LE RUBAN : les mentions défilent, plus vite quand on fait défiler la page --}}
        <div class="tt-ticker" aria-hidden="true" data-tt-ticker>
            <div class="tt-ticker__track" data-tt-ticker-track>
                @foreach (range(1, 2) as $copy)
                    <p class="tt-ticker__run">
                        @foreach ($sheets as $sheet)
                            <span>{{ $sheet['name'] }}</span><i></i>
                        @endforeach
                    </p>
                @endforeach
            </div>
        </div>

        {{-- 3. LES FICHES : une fiche par mention ; sur grand écran la page s'arrête et les fiches défilent sur le côté --}}
        <section id="mentions" class="tt-sheets" data-tt-sheets aria-labelledby="tt-sheets-title">
            <div class="tt-sheets__stage" data-tt-stage>
                <div class="container-site tt-sheets__head">
                    <div>
                        <p class="eyebrow mb-3">{{ __('timetables.sheets.eyebrow') }}</p>
                        <h2 id="tt-sheets-title" class="tt-h2">{{ __('timetables.sheets.title') }}</h2>
                    </div>
                    @if ($count > 1)
                        <ol class="tt-index" aria-label="{{ __('timetables.sheets.index') }}">
                            @foreach ($sheets as $sheet)
                                <li><a href="#mention-{{ $sheet['key'] }}" data-tt-jump="{{ $loop->index }}" aria-label="{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} — {{ $sheet['name'] }}" title="{{ $sheet['name'] }}">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</a></li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                <div class="tt-viewport" data-tt-viewport>
                    <ul class="tt-track" data-tt-track>
                        @foreach ($sheets as $sheet)
                            <li class="tt-card" id="mention-{{ $sheet['key'] }}" data-tt-card style="--accent: var(--tt-tone-{{ $loop->index % 5 }}); --accent-ink: var(--tt-tone-{{ $loop->index % 5 }}-ink)">
                                <article class="tt-card__sheet" data-tt-sheet>
                                    <p class="tt-card__no" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                                    <div class="tt-art" aria-hidden="true" data-tt-art>
                                        <div class="tt-art__grid">
                                            @foreach ($sheet['art'] as [$c, $r, $s, $t])
                                                <i class="tt-block is-{{ $tones[$t] }}" style="--c: {{ $c + 1 }}; --r: {{ $r + 1 }}; --s: {{ $s }}" data-tt-art-block></i>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="tt-card__body">
                                        @if ($sheet['faculty'])<p class="tt-card__eyebrow">{{ $sheet['faculty'] }}</p>@endif
                                        <h3 class="tt-card__title">{{ $sheet['name'] }}</h3>
                                        <p class="tt-card__meta">
                                            <span>PDF</span>
                                            @if ($sheet['size'])<span>{{ __('timetables.size', ['size' => $sheet['size']]) }}</span>@endif
                                        </p>
                                        <div class="tt-card__actions">
                                            <a href="{{ $sheet['url'] }}" class="tt-open" target="_blank" rel="noopener noreferrer">
                                                {{ __('timetables.open') }}<span class="sr-only"> — {{ $sheet['name'] }} ({{ __('timetables.new_tab') }})</span>
                                                <x-ico name="arrow-up-right" class="size-4" />
                                            </a>
                                            @if (str_starts_with($sheet['url'], '/'))
                                                <a href="{{ $sheet['url'] }}" class="tt-save" download>{{ __('timetables.download') }}<span class="sr-only"> — {{ $sheet['name'] }}</span></a>
                                            @endif
                                            @if ($sheet['formationUrl'])
                                                <a href="{{ $sheet['formationUrl'] }}" class="tt-more">{{ __('Voir la formation') }}<span class="sr-only"> — {{ $sheet['name'] }}</span></a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($count > 1)
                    <div class="container-site tt-progress" aria-hidden="true" data-tt-progress>
                        <span class="tt-progress__count" data-tt-count>01 / {{ str_pad($count, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="tt-progress__line"><i data-tt-bar></i></span>
                        <span class="tt-progress__name" data-tt-name>{{ $sheets->first()['name'] }}</span>
                    </div>
                @endif
            </div>
        </section>
    @else
        <section class="tt-empty" aria-labelledby="tt-empty-title">
            <div class="container-site">
                <h2 id="tt-empty-title" class="tt-h2">{{ __('timetables.empty.title') }}</h2>
                <p>{{ __('timetables.empty.text') }}</p>
            </div>
        </section>
    @endif

    {{-- Le texte de la page, s'il en reste (titres, explications saisis dans le back-office) --}}
    @if (count($blocks))
        <div class="tt-rest"><x-blocks :blocks="$blocks" /></div>
    @endif

    {{-- 4. L'INVITATION --}}
    <section class="tt-invite on-dark" data-tt-invite aria-labelledby="tt-invite-title">
        <div class="container-site">
            <p class="eyebrow mb-6 text-sun-400!" data-reveal>{{ __('timetables.invite.eyebrow') }}</p>
            <h2 id="tt-invite-title" class="tt-invite__text" data-tt-invite-text>{{ __('timetables.invite.text') }}</h2>
            <p class="mt-10" data-reveal><x-button :href="$contact" variant="sun" arrow class="btn-square">{{ __('timetables.invite.cta') }}</x-button></p>
        </div>
        <svg class="tt-invite__line" viewBox="0 0 1000 60" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path d="M0 30 H1000" fill="none" data-tt-line />
        </svg>
    </section>
</x-layouts.app>
