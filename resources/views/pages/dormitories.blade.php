@php
    // Schéma du rez-de-chaussée du nouveau dortoir (projet R+2), d'après le texte de présentation : huit chambres pour quatre étudiants
    // (toilettes privatives), un espace de réception central, une salle à manger et deux chambres de la Maison du Précepteur.
    // Les emplacements sont illustratifs.
    $cells = [
        ['r1', 'room', __('Chambre'), __('4 étudiants')], ['r2', 'room', __('Chambre'), __('4 étudiants')],
        ['r3', 'room', __('Chambre'), __('4 étudiants')], ['r4', 'room', __('Chambre'), __('4 étudiants')],
        ['rc', 'hall', __('Espace de réception'), __('au centre')], ['dm', 'hall', __('Salle à manger'), 'conviviale'],
        ['r5', 'room', __('Chambre'), __('4 étudiants')], ['r6', 'room', __('Chambre'), __('4 étudiants')],
        ['r7', 'room', __('Chambre'), __('4 étudiants')], ['r8', 'room', __('Chambre'), __('4 étudiants')],
        ['p1', 'tutor', __('Maison du Précepteur'), 'chambre'], ['p2', 'tutor', __('Maison du Précepteur'), 'chambre'],
    ];

    $houses = [
        [
            'key' => 'boys', 'eyebrow' => __('Dortoir des garçons'), 'count' => 80, 'unit' => __('étudiants'),
            'rooms' => __('Des chambres de 3 à 4 étudiants.'), 'photo' => $boys,
            'alt' => __('La résidence des garçons, un bâtiment bas au milieu des arbres'),
        ],
        [
            'key' => 'girls', 'eyebrow' => __('Dortoir des filles'), 'count' => 68, 'unit' => __('étudiantes'),
            'rooms' => __('Des chambres de 4 à 5 étudiantes.'), 'photo' => $girls,
            'alt' => __('La résidence des filles, bâtiment de plain-pied entouré de haies'),
        ],
    ];

    // Mur de photos : un mot du texte de présentation par photo (garçons d'abord, puis filles), dans l'ordre des figures.
    $wallCaptions = [__('Camaraderie'), __('Apprentissage'), __('Concentration'), __('Amitié'), __('Croissance personnelle')];
    $wallTilts = [-3.5, 2.5, -2, 3, -1.5];

    $outline = fn (string $name) => '<svg class="dor-outline" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect x="2" y="2" width="396" height="56" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" rx="3"/></svg>';
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. LA PORTE : une arche sur l'allée du campus ; en défilant, elle s'ouvre jusqu'à remplir l'écran (animations : resources/js/animations/dormitories.js) --}}
    <section class="dor-hero" data-dormitories data-dor-hero aria-labelledby="dor-title">
        <div class="dor-hero__stage" data-dor-stage>
            <span class="dor-sun" data-dor-sun aria-hidden="true"></span>

            <div class="dor-hero__text container-site" data-dor-text>
                <x-breadcrumbs :items="$trail" />
                <h1 id="dor-title" class="dor-title" data-dor-hide data-dor-title>{{ __('Dortoirs') }}</h1>
                <p class="dor-hero__lead" data-dor-hide data-dor-fade>{{ __('Votre second foyer') }}</p>
            </div>

            <div class="dor-door" data-dor-door aria-hidden="true">
                @if ($room ?? $path)
                    <div class="dor-door__photo" data-dor-door-photo>
                        <x-picture :asset="$room ?? $path" conversion="hero" sizes="100vw" eager decorative />
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- 2. LE CŒUR DE LA VIE ÉTUDIANTE --}}
    <section class="dor-statement" data-dor-statement aria-labelledby="dor-statement-title">
        <div class="container-site grid gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:items-end lg:gap-20">
            <h2 id="dor-statement-title" class="dor-statement__text" data-dor-statement-text>{{ __('Bien plus qu\'un simple lieu d\'hébergement : le cœur de la vie étudiante sur le campus.') }}</h2>
            <div class="grid gap-8">
                <p class="max-w-md text-lead text-ink-700" data-reveal>{{ __('Un cadre de vie sécurisé, propice à l\'étude et au développement communautaire et spirituel.') }}</p>
                @if ($room && $path)
                    <div class="dor-lane" data-dor-lane data-reveal>
                        <x-picture :asset="$path" conversion="card" sizes="(min-width: 1024px) 28rem, 90vw" :alt="__('Des étudiants marchent sur l\'allée du campus, bordée de pins')" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- 3. LES DEUX RÉSIDENCES : chiffres qui montent, cartes qui se croisent en défilant --}}
    <section class="dor-houses" data-dor-houses aria-label="{{ __('Les deux résidences') }}">
        <div class="container-site grid gap-14 lg:grid-cols-2 lg:gap-10">
            @foreach ($houses as $house)
                <article class="dor-house dor-house--{{ $house['key'] }}" data-dor-house>
                    <div class="dor-house__photo" data-dor-house-photo>
                        @if ($house['photo'])
                            <x-picture :asset="$house['photo']" conversion="hero" sizes="(min-width: 1024px) 50vw, 100vw" :alt="$house['alt']" />
                        @endif
                    </div>
                    <div class="dor-house__body">
                        <p class="eyebrow">{{ $house['eyebrow'] }}</p>
                        <p class="dor-house__count"><span data-dor-count="{{ $house['count'] }}">{{ $house['count'] }}</span><small>{{ $house['unit'] }}</small></p>
                        <p class="dor-house__rooms">{{ $house['rooms'] }}</p>
                        @if ($house['key'] === 'girls')
                            <p class="dor-house__note">{{ __('Des projets sont activement menés pour optimiser et moderniser le dortoir, notamment en réduisant le nombre d\'occupantes par chambre, pour un meilleur confort et une meilleure concentration académique.') }}</p>
                            <p class="dor-house__note">{{ __('Un lieu de vie qui encourage la discipline, le soutien mutuel et les échanges constructifs, dans un cadre sécurisé.') }}</p>
                        @else
                            <p class="dor-house__note">{{ __('Capacité actuelle, dans les structures existantes.') }}</p>
                            <a href="#dor-plan" class="link-arrow mt-6 w-fit">{{ __('Voir le nouveau dortoir') }}</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- 3 bis. LE MUR : des photos des chambres, épinglées de travers ; on peut les déplacer à la main --}}
    @if ($wall->isNotEmpty())
        <section class="dor-wall" data-dor-wall aria-labelledby="dor-wall-title">
            <div class="container-site">
                <h2 id="dor-wall-title" class="dor-wall__title" data-reveal>{{ __('Dans les chambres') }}</h2>
                <p class="dor-wall__hint" data-dor-hint hidden>{{ __('Faites glisser les photos') }}</p>
                <div class="dor-wall__board" data-dor-board>
                    @foreach ($wall as $i => $photo)
                        <figure class="dor-polaroid" data-dor-polaroid data-tilt="{{ $wallTilts[$i] ?? 0 }}">
                            <div class="dor-polaroid__img"><x-picture :asset="$photo" conversion="card" sizes="(min-width: 1024px) 24rem, 80vw" /></div>
                            <figcaption>{{ $wallCaptions[$i] ?? '' }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 4. LE PROJET R+2 : le bâtiment se construit et ses fenêtres s'allument (schéma illustratif) --}}
    <section id="dor-plan" class="dor-plan on-dark" data-dor-plan aria-labelledby="dor-plan-title">
        <div class="dor-plan__stage" data-dor-plan-stage>
            <div class="container-site grid items-center gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                <div>
                    <p class="eyebrow text-sun-400!">{{ __('Nouveau dortoir') }}</p>
                    <h2 id="dor-plan-title" class="dor-plan__title" data-reveal>{{ __('Le projet R+2 prend forme') }}</h2>
                    <p class="mt-6 max-w-md text-lead text-white/85" data-reveal data-reveal-delay="0.1">{{ __('Le rez-de-chaussée est en cours d\'achèvement, une étape cruciale.') }}</p>
                    <p class="mt-5 max-w-md text-white/75" data-dor-vision>{{ __('L\'achèvement du projet vise à offrir des chambres modernes et confortables, renforçant l\'entraide, la collaboration et le développement de liens sociaux positifs.') }}</p>
                    @if ($project)
                        <x-button :href="route('projects.show', $project->slug)" variant="sun" arrow class="btn-square mt-9" data-dor-vision>{{ __('Soutenir ce projet') }}</x-button>
                    @endif
                </div>

                <figure class="dor-building" data-dor-building aria-label="{{ __('Schéma illustratif du nouveau dortoir : le rez-de-chaussée et les deux étages prévus') }}">
                    <div class="dor-floor dor-floor--future" data-dor-future><span>{{ __('R+2') }}</span>{!! $outline('r2') !!}</div>
                    <div class="dor-floor dor-floor--future" data-dor-future><span>{{ __('R+1') }}</span>{!! $outline('r1') !!}</div>
                    <ul class="dor-floor dor-floor--ground" data-dor-ground role="list">
                        @foreach ($cells as [$area, $kind, $label, $hint])
                            <li class="dor-cell dor-cell--{{ $kind }}" data-dor-cell style="--a: {{ $area }}"><strong>{{ $label }}</strong><span>{{ $hint }}</span></li>
                        @endforeach
                    </ul>
                    <figcaption class="dor-building__note">{{ __('Rez-de-chaussée · schéma illustratif, hors échelle. Toilettes privatives dans chaque chambre.') }}</figcaption>
                </figure>
            </div>
        </div>
    </section>

    {{-- 5. LA NUIT TOMBE : les fenêtres du campus s'allument ; pour prolonger --}}
    <section class="dor-night on-dark" data-dor-night aria-labelledby="dor-night-title">
        <div class="dor-skyline" aria-hidden="true">
            @foreach (range(1, 4) as $block)
                <span class="dor-skyline__block" style="--w: {{ [14, 22, 16, 20][$block - 1] }}%; --h: {{ [46, 70, 38, 58][$block - 1] }}%">
                    @foreach (range(1, 18) as $window)<i data-dor-window></i>@endforeach
                </span>
            @endforeach
        </div>
        <div class="container-site relative">
            @if ($links)
            <h2 id="dor-night-title" class="dor-night__title" data-reveal>{{ __('À découvrir aussi') }}</h2>
            <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:max-w-3xl" role="list">
                @foreach ($links as [$href, $label])
                    <li data-reveal data-reveal-delay="{{ 0.1 * $loop->iteration }}">
                        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="dor-link">
                            <span class="dor-link__label">{{ $label }}</span>
                            <span class="dor-link__hint">{{ __('Sur Facebook') }} <x-ico name="arrow-up-right" class="size-4" /><span class="sr-only"> {{ __('(nouvel onglet)') }}</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @else
            <h2 id="dor-night-title" class="sr-only">{{ __('Fin de la page') }}</h2>
            @endif
        </div>
    </section>
</x-layouts.app>
