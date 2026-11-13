@php
    // « Centre de recherche (ZRC) » : le sigle entre parenthèses devient une marque à part, il n'est écrit nulle part en dur.
    preg_match('/^(.*?)\s*\((.+)\)\s*$/u', (string) $page->title, $parts);
    $name = $parts[1] ?? $page->title;
    $sigil = $parts[2] ?? null;

    // Le réseau du héros : points (x, y dans une boîte de 1000 × 600) et liens entre eux. Décor pur, aucun texte.
    $points = [[80, 420], [190, 250], [260, 470], [340, 120], [420, 330], [520, 200], [580, 470], [650, 90], [700, 300], [780, 420], [860, 180], [930, 360], [150, 90], [40, 260], [480, 60], [760, 560], [330, 560], [960, 520], [600, 330], [240, 350]];
    $links = [[0, 1], [1, 2], [1, 3], [2, 4], [3, 5], [4, 5], [4, 6], [5, 7], [5, 8], [6, 9], [8, 9], [8, 10], [9, 11], [10, 11], [3, 12], [12, 1], [13, 0], [13, 1], [5, 14], [14, 3], [6, 15], [2, 16], [11, 17], [9, 17], [4, 18], [18, 8], [19, 4], [19, 2], [19, 1], [18, 6]];

    // Le directeur et son domaine : la phrase n'apparaît que si la fiche de l'équipe le dit (fonction « Directeur … / Responsable domaine Sciences sociales »).
    $director = $members->first(fn ($m) => str_starts_with(mb_strtolower($m->getRawOriginal('job_title')), 'directeur'));
    $directsDomain = $director && $domain && str_contains(mb_strtolower($director->getRawOriginal('job_title')), mb_strtolower($domain->getRawOriginal('name')));
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. L'OBSERVATOIRE : un réseau de points se tisse, le titre se lève lettre à lettre (animations : resources/js/animations/research.js) --}}
    <section class="res-hero on-dark" data-research data-res-hero aria-labelledby="res-title">
        <div class="res-hero__grid" aria-hidden="true"></div>
        <svg class="res-net" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" data-res-net>
            <g data-res-layer="far">
                @foreach ($links as [$a, $b])
                    <line x1="{{ $points[$a][0] }}" y1="{{ $points[$a][1] }}" x2="{{ $points[$b][0] }}" y2="{{ $points[$b][1] }}" data-res-edge />
                @endforeach
            </g>
            <g data-res-layer="near">
                @foreach ($points as $i => [$x, $y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ [4, 6, 3.5, 5, 7][$i % 5] }}" @class(['is-gold' => $i % 4 === 0]) data-res-node />
                @endforeach
            </g>
        </svg>

        <div class="container-site">
            <x-breadcrumbs :items="$trail" dark />
        </div>

        <div class="container-site res-hero__body">
            @if ($sigil)
                <p class="res-sigil" data-res-hide data-res-fade><span class="res-sigil__ring" aria-hidden="true" data-res-ring></span><span>{{ $sigil }}</span></p>
            @endif
            <p class="eyebrow mb-4 text-sun-400!" data-res-hide data-res-fade>{{ __('Université Adventiste Zurcher') }}</p>
            <h1 id="res-title" class="res-title" data-res-title>{{ $name }}</h1>
            @if ($page->summary)
                <p class="res-lead" data-res-hide data-res-fade>{{ $page->summary }}</p>
            @endif
            <a href="#res-statement" class="res-cue" data-res-hide data-res-fade><span aria-hidden="true"></span>{{ __('Faites défiler') }}</a>
        </div>
    </section>

    {{-- 2. LA PHRASE : écran épinglé, les mots s'allument un à un pendant que le fil se tend entre les points --}}
    <section id="res-statement" class="res-statement on-dark" data-res-statement aria-labelledby="res-statement-title">
        <div class="container-site res-statement__inner">
            <p class="eyebrow mb-8 text-sun-400!" id="res-statement-title">{{ __('La recherche, dans la formation') }}</p>
            <p class="res-quote" data-res-quote>{{ __('Grâce à des méthodes d\'apprentissage interactives, des travaux dirigés, des stages et des activités de recherche, chaque étudiant bénéficie d\'un parcours sur mesure.') }}</p>
            <svg class="res-thread" viewBox="0 0 1000 40" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <path d="M0 22 C 120 4, 200 38, 330 20 S 560 2, 680 22 S 880 38, 1000 14" fill="none" data-res-thread />
            </svg>
        </div>
    </section>

    {{-- 3. LES PERSONNES : la fiche de l'équipe de chaque personne du centre --}}
    @if ($members->isNotEmpty())
        <section class="res-people" data-res-people aria-labelledby="res-people-title">
            <div class="container-site">
                <p class="eyebrow mb-4" data-reveal>{{ __('Les personnes') }}</p>
                <h2 id="res-people-title" class="res-h2" data-reveal>{{ __('L\'équipe du centre') }}</h2>
                <ul class="res-people__list">
                    @foreach ($members as $member)
                        <li class="res-person" data-res-person>
                            <div class="res-person__photo" data-res-photo>
                                @if ($member->photo)
                                    <x-picture :asset="$member->photo" conversion="portrait" sizes="(min-width: 768px) 22rem, 80vw" :alt="__('Portrait de :name', ['name' => $member->name])" />
                                @else
                                    <span class="res-person__mono" aria-hidden="true">{{ collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                                @endif
                            </div>
                            <div class="res-person__text">
                                <h3 class="res-person__name">{{ $member->name }}</h3>
                                @if ($member->job_title)<p class="res-person__role">{{ $member->job_title }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-10" data-reveal><a href="{{ url('/equipe') }}" class="link-arrow">{{ __('Voir toute l\'équipe') }} <x-ico name="arrow-right" class="size-4" /></a></p>
            </div>
        </section>
    @endif

    {{-- 4. LE DOMAINE : les mentions du domaine, reliées comme les points d'un réseau --}}
    @if ($domain && $domain->formations->isNotEmpty())
        <section class="res-domain" data-res-domain aria-labelledby="res-domain-title">
            <div class="container-site">
                <p class="eyebrow mb-4" data-reveal>{{ __('Le domaine') }}</p>
                <h2 id="res-domain-title" class="res-h2" data-reveal>{{ $domain->name }}</h2>
                @if ($directsDomain)
                    <p class="res-domain__lead" data-reveal>{{ __('Le centre est dirigé par :name, responsable du domaine :domain.', ['name' => $director->name, 'domain' => $domain->name]) }}</p>
                @endif
                <p class="res-domain__hint" data-reveal>{{ __('Mentions du domaine') }}</p>
                <ol class="res-chain" data-res-chain>
                    @foreach ($domain->formations as $formation)
                        <li class="res-chain__item" data-res-node-item>
                            <a href="{{ $formation->url() }}" class="res-chain__link"><span class="res-chain__dot" aria-hidden="true"></span><span>{{ $formation->mention ?: $formation->title }}</span></a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- 5. LE MASTER : le texte de présentation des parcours de l'UAZ --}}
    <section class="res-master on-dark" data-res-master aria-labelledby="res-master-title">
        <svg class="res-master__net" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" data-res-master-net>
            @foreach (array_slice($links, 0, 14) as [$a, $b])
                <line x1="{{ $points[$a][0] }}" y1="{{ $points[$a][1] }}" x2="{{ $points[$b][0] }}" y2="{{ $points[$b][1] }}" />
            @endforeach
            @foreach (array_slice($points, 0, 12) as [$x, $y])
                <circle cx="{{ $x }}" cy="{{ $y }}" r="4" />
            @endforeach
        </svg>
        <div class="container-site res-master__inner">
            <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ __('Se spécialiser') }}</p>
            <h2 id="res-master-title" class="res-master__title" data-reveal>{{ __('Master') }}</h2>
            <p class="res-master__text" data-reveal>{{ __('about.paths.items.1.text') }}</p>
            <p class="mt-8" data-reveal><a href="{{ url('/admissions/master') }}" class="btn btn-gold">{{ __('Admission en Master') }}</a></p>
        </div>
    </section>

    {{-- 6. LA SUITE : ce que l'UAZ ajoute dans le back-office (blocs de la page) ; en attendant, une promesse sobre --}}
    <section class="res-more" aria-labelledby="res-more-title">
        <div class="container-site">
            @if ($blocks->isNotEmpty())
                <h2 id="res-more-title" class="sr-only">{{ $name }}</h2>
                <x-blocks :blocks="$blocks->all()" />
            @else
                <div class="res-soon" data-reveal>
                    <span class="res-soon__dot" aria-hidden="true"></span>
                    <p id="res-more-title">{{ __('La présentation détaillée du centre (missions, axes de recherche, publications) sera publiée ici.') }}</p>
                </div>
            @endif
        </div>
    </section>

    {{-- 7. L'INVITATION --}}
    <section class="res-invite on-dark" data-res-invite aria-labelledby="res-invite-title">
        <div class="container-site">
            <h2 id="res-invite-title" class="res-invite__text" data-res-invite-text>{{ __('Une question sur la recherche à l\'UAZ ?') }}</h2>
            <p class="mt-10" data-reveal><x-button :href="url('/contact?sujet='.rawurlencode($name))" variant="gold" arrow>{{ __('Écrire à l\'UAZ') }}</x-button></p>
        </div>
    </section>
</x-layouts.app>
