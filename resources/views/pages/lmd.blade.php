@php
    $levels = $data['levels'];
    $licence = $levels['licence'];
    $master = $levels['master'];
    $doctorat = $levels['doctorat'];
    $licenceYears = (int) $licence['years'];
    $masterYears = (int) $master['years'];
    $total = (int) $master['total'];
    $share = round($licence['ects'] / $total * 100, 2); // part de la Licence dans les 300 crédits : sert à dessiner la jauge

    // Les trois lettres du héros : L, M, D (initiales des grades).
    $letters = [
        ['key' => 'licence', 'letter' => mb_substr($licence['title'], 0, 1), 'name' => $licence['title'], 'grade' => __('lmd.grade', ['years' => $licenceYears])],
        ['key' => 'master', 'letter' => mb_substr($master['title'], 0, 1), 'name' => $master['title'], 'grade' => __('lmd.grade', ['years' => $licenceYears + $masterYears])],
        ['key' => 'doctorat', 'letter' => mb_substr($doctorat['title'], 0, 1), 'name' => $doctorat['title'], 'grade' => __('lmd.after_master')],
    ];

    // L'escalier : trois marches pleines (Licence, Master, puis la marche du Doctorat en pointillés).
    $stair = 'M20 410 H200 V310 H380 V210 H560';
    $stairNext = 'M560 210 V110 H590';
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. LES TROIS LETTRES : L, M, D, géantes ; elles suivent le pointeur et s'allument au survol (animations : resources/js/animations/lmd.js) --}}
    <header class="lmd-hero on-dark" data-lmd data-lmd-hero aria-labelledby="lmd-title">
        <div class="lmd-hero__grid" aria-hidden="true"></div>

        <div class="container-site">
            <x-breadcrumbs :items="$trail" dark />
        </div>

        <div class="container-site lmd-hero__body">
            <p class="eyebrow mb-5 text-sun-400!" data-lmd-hide data-lmd-fade>{{ __('lmd.hero.eyebrow') }}</p>
            <h1 id="lmd-title" class="lmd-title" data-lmd-title>{{ $page->title }}</h1>
            <p class="lmd-lead" data-lmd-hide data-lmd-fade>{{ $data['intro'] }}</p>

            <ol class="lmd-letters">
                @foreach ($letters as $item)
                    <li>
                        <a href="#{{ $item['key'] }}" class="lmd-letter" data-lmd-letter="{{ $loop->iteration }}">
                            <span class="lmd-letter__big" aria-hidden="true">{{ $item['letter'] }}</span>
                            <span class="lmd-letter__name">{{ $item['name'] }}</span>
                            <span class="lmd-letter__grade">{{ $item['grade'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>

            <a href="#montee" class="lmd-cue" data-lmd-hide data-lmd-fade><span aria-hidden="true"></span>{{ __('lmd.hero.cue') }}</a>
        </div>
    </header>

    {{-- 2. LA MONTÉE : une marche après l'autre, la jauge des crédits ECTS se remplit (180, puis 300) ; écran arrêté sur grand écran --}}
    <section id="montee" class="lmd-climb on-dark" data-lmd-climb aria-labelledby="lmd-climb-title">
        <div class="lmd-climb__stage" data-lmd-stage>
            <div class="container-site lmd-climb__inner">
                <div class="lmd-climb__text">
                    <p class="eyebrow mb-4 text-sun-400!">{{ __('lmd.climb.eyebrow') }}</p>
                    <h2 id="lmd-climb-title" class="lmd-h2">{{ __('lmd.climb.title') }}</h2>

                    <ol class="lmd-steps" data-lmd-steps>
                        <li class="lmd-step" data-lmd-step>
                            <p class="lmd-step__kicker">{{ $licence['kicker'] }}</p>
                            <h3 class="lmd-step__title">{{ $licence['title'] }} <span>{{ __('lmd.grade', ['years' => $licenceYears]) }}</span></h3>
                            <p class="lmd-step__facts">
                                <span>{{ trans_choice('lmd.climb.years', $licenceYears) }}</span>
                                <span>{{ trans_choice('lmd.climb.semesters', $licence['semesters']) }}</span>
                                <span>{{ __('lmd.climb.ects', ['count' => $licence['ects']]) }}</span>
                            </p>
                            <p class="lmd-step__text">{{ $licence['text'] }}</p>
                        </li>
                        <li class="lmd-step" data-lmd-step>
                            <p class="lmd-step__kicker">{{ $master['kicker'] }}</p>
                            <h3 class="lmd-step__title">{{ $master['title'] }} <span>{{ __('lmd.grade', ['years' => $licenceYears + $masterYears]) }}</span></h3>
                            <p class="lmd-step__facts">
                                <span>{{ trans_choice('lmd.climb.years', $masterYears) }} ({{ implode(' · ', $master['stages']) }})</span>
                                <span>{{ __('lmd.climb.ects', ['count' => $master['ects']]) }}</span>
                                <span>{{ __('lmd.climb.total', ['count' => $total]) }}</span>
                            </p>
                            <p class="lmd-step__text">{{ $master['text'] }}</p>
                        </li>
                        <li class="lmd-step" data-lmd-step>
                            <p class="lmd-step__kicker">{{ $doctorat['kicker'] }}</p>
                            <h3 class="lmd-step__title">{{ $doctorat['title'] }} <span>{{ __('lmd.after_master') }}</span></h3>
                            <p class="lmd-step__text">{{ $doctorat['text'] }}</p>
                        </li>
                    </ol>
                </div>

                {{-- L'escalier et la jauge : décor, tout le texte est dans les marches à gauche --}}
                <div class="lmd-art" aria-hidden="true" data-lmd-art>
                    <svg viewBox="0 0 600 440" class="lmd-stair" focusable="false">
                        <path class="lmd-stair__base" d="{{ $stair }}" />
                        <path class="lmd-stair__line" d="{{ $stair }}" data-lmd-stair />
                        <path class="lmd-stair__next" d="{{ $stairNext }}" data-lmd-next />
                        <text x="20" y="436" class="lmd-stair__label">{{ __('lmd.climb.start') }}</text>
                        <text x="110" y="396" class="lmd-stair__label is-gold" text-anchor="middle">{{ $licence['title'] }}</text>
                        <text x="290" y="296" class="lmd-stair__label is-gold" text-anchor="middle">{{ $master['title'] }}</text>
                        <text x="470" y="196" class="lmd-stair__label is-gold" text-anchor="middle">{{ $doctorat['title'] }}</text>
                        <circle class="lmd-stair__marker" r="9" cx="20" cy="410" data-lmd-marker />
                    </svg>

                    <div class="lmd-gauge" data-share="{{ $share }}">
                        <svg viewBox="0 0 160 160" focusable="false">
                            <circle class="lmd-gauge__track" cx="80" cy="80" r="66" />
                            <circle class="lmd-gauge__arc is-licence" cx="80" cy="80" r="66" pathLength="100" style="stroke-dasharray: {{ $share }} 100" data-lmd-arc="licence" />
                            <circle class="lmd-gauge__arc is-master" cx="80" cy="80" r="66" pathLength="100" style="stroke-dasharray: {{ 100 - $share }} 100; stroke-dashoffset: -{{ $share }}" data-lmd-arc="master" />
                        </svg>
                        <p class="lmd-gauge__value"><b data-lmd-count data-final="{{ $total }}" data-licence="{{ $licence['ects'] }}">{{ $total }}</b><span>{{ __('lmd.climb.gauge') }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 3. LA LICENCE : les mentions --}}
    <section id="licence" class="lmd-licence" data-lmd-mentions aria-labelledby="lmd-licence-title">
        <div class="container-site">
            <p class="eyebrow mb-4" data-reveal>{{ $licence['title'] }} · {{ __('lmd.grade', ['years' => $licenceYears]) }}</p>
            <h2 id="lmd-licence-title" class="lmd-h2" data-reveal>{{ __('lmd.licence.title') }}</h2>
            <div class="lmd-intro" data-reveal data-reveal-delay="0.1">
                @foreach ($data['licence_text'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            </div>

            <ul class="lmd-mentions">
                @foreach ($data['licence_mentions'] as $i => $mention)
                    <li class="lmd-mention" data-lmd-mention>
                        <span class="lmd-mention__no" aria-hidden="true">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="lmd-mention__name">
                            @if ($links[$i] ?? null)<a href="{{ $links[$i] }}" class="stretched-link">{{ $mention['name'] }}</a>@else{{ $mention['name'] }}@endif
                        </h3>
                        <p class="lmd-mention__label">{{ __('lmd.tracks') }}</p>
                        <ul class="lmd-mention__tracks">
                            @foreach ($mention['tracks'] as $track)<li>{{ $track }}</li>@endforeach
                        </ul>
                        @if ($links[$i] ?? null)<span class="lmd-mention__more" aria-hidden="true">{{ __('lmd.link') }} <x-ico name="arrow-up-right" class="size-4" /></span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 4. LE MASTER : M1 puis M2, et ses mentions --}}
    <section id="master" class="lmd-master on-dark" data-lmd-mentions aria-labelledby="lmd-master-title">
        <div class="container-site">
            <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ $master['title'] }} · {{ __('lmd.grade', ['years' => $licenceYears + $masterYears]) }}</p>
            <h2 id="lmd-master-title" class="lmd-h2" data-reveal>{{ __('lmd.master.title') }}</h2>
            <p class="lmd-intro-dark" data-reveal data-reveal-delay="0.1">{{ $data['master_text'] }}</p>

            <ol class="lmd-stages" aria-label="{{ __('lmd.master.stages') }}" data-lmd-stages>
                @foreach ($master['stages'] as $stage)
                    <li class="lmd-stage" data-lmd-stage-item><b>{{ $stage }}</b></li>
                @endforeach
            </ol>

            <ul class="lmd-masters">
                @foreach ($data['master_mentions'] as $mention)
                    <li class="lmd-master-card" data-lmd-mention>
                        <h3 class="lmd-master-card__name">{{ $mention['name'] }}</h3>
                        <p class="lmd-mention__label">{{ __('lmd.tracks') }}</p>
                        <ul class="lmd-mention__tracks">
                            @foreach ($mention['tracks'] as $track)<li>{{ $track }}</li>@endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 5. LE DOCTORAT : la suite, sans rien promettre de plus que le texte de la page --}}
    <section id="doctorat" class="lmd-doc" data-lmd-doc aria-labelledby="lmd-doc-title">
        <div class="container-site">
            <p class="eyebrow mb-6" data-reveal>{{ $doctorat['title'] }} · {{ $doctorat['kicker'] }}</p>
            <h2 id="lmd-doc-title" class="lmd-doc__text" data-lmd-doc-text>{{ $doctorat['text'] }}</h2>
            <p class="mt-10 flex flex-wrap gap-4" data-reveal>
                <x-button :href="url('/admissions/licence')" variant="navy" arrow class="btn-square">{{ __('lmd.cta.licence') }}</x-button>
                <x-button :href="url('/admissions/master')" variant="outline">{{ __('lmd.cta.master') }}</x-button>
                <x-button :href="url('/formations')" variant="outline">{{ __('lmd.cta.formations') }}</x-button>
            </p>
        </div>
    </section>
</x-layouts.app>
