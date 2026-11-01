@php
    // Horaires de l'affiche officielle de la bibliothèque (jours : 0 = dimanche … 6 = samedi). Le samedi n'y figure pas : on ne l'invente pas.
    $schedule = [
        'open' => [
            ['label' => 'Dimanche', 'days' => [0], 'slots' => [['08:00', '12:00'], ['14:00', '17:30']]],
            ['label' => 'Lundi', 'days' => [1], 'slots' => [['09:30', '11:00'], ['13:00', '18:30']]],
            ['label' => 'Mardi – jeudi', 'days' => [2, 3, 4], 'slots' => [['08:00', '18:30']]],
            ['label' => 'Vendredi', 'days' => [5], 'slots' => [['08:00', '12:00']]],
        ],
        'loan' => [
            ['label' => 'Dimanche', 'days' => [0], 'slots' => [], 'note' => 'Aucun service de prêt ni de prolongation'],
            ['label' => 'Lundi', 'days' => [1], 'slots' => [['09:30', '10:45'], ['13:30', '17:15']]],
            ['label' => 'Mardi – jeudi', 'days' => [2, 3, 4], 'slots' => [['08:00', '11:45'], ['13:30', '17:15']]],
            ['label' => 'Vendredi', 'days' => [5], 'slots' => [['08:00', '11:45']]],
        ],
    ];
    $hour = function (string $time): string {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $m ? "$h h ".str_pad((string) $m, 2, '0', STR_PAD_LEFT) : "$h h";
    };
    $range = fn (array $slot): string => $hour($slot[0]).' – '.$hour($slot[1]);

    $pillars = [
        ['Savoir', 'Ici, chaque livre, chaque ressource électronique, chaque moment de lecture est une porte ouverte sur la découverte et la croissance intellectuelle.'],
        ['Réflexion', 'Un environnement accueillant et calme, où trouver la sérénité nécessaire pour étudier, réfléchir et approfondir votre compréhension du monde et de la Parole de Dieu.'],
        ['Inspiration', 'À la bibliothèque Paul Pichot, chaque visite est une expérience, chaque lecture est une lumière, et chaque étudiant est un chercheur en devenir.'],
    ];

    // Le rayonnage : chaque « livre » ouvre une fiche. Les textes viennent de la présentation de la bibliothèque.
    $books = [
        ['Espace calme', 'Un lieu pour lire', 'Un environnement accueillant et calme, pensé pour étudier, réfléchir et lire sans être dérangé.', null, '#7a2e3d', '#4a1623', 17.5],
        ['Ressources fiables', 'Des ressources à jour', 'Des ressources actualisées et fiables, accessibles surtout sur place et peu à peu en ligne, pour soutenir vos études, vos recherches et vos projets académiques.', null, '#16204f', '#0b1233', 15],
        ['Ebooks', 'Des livres numériques', 'Chaque ressource électronique est une porte ouverte sur la découverte : parcourez la collection de livres numériques de la bibliothèque.', ['Parcourir les ebooks', 'http://ebookcentral.proquest.com/lib/zurcheradve/home.action'], '#b0915a', '#7f6535', 19],
        ['Bases de données', 'Savoir où chercher', 'L\'équipe vous accompagne dans l\'utilisation des bases de données.', null, '#2f5d62', '#173236', 16],
        ['Accompagnement', 'Une équipe disponible', 'Une équipe pour vous guider dans la recherche d\'information et le développement de vos compétences documentaires.', null, '#8a5a35', '#55341a', 18],
    ];
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. ENTRER : l'enseigne se rapproche, la porte s'ouvre en iris sur la salle de lecture (défilement épinglé, resources/js/animations/library.js) --}}
    <section class="lib-intro on-dark" data-library data-lib-intro aria-labelledby="lib-title">
        <div class="lib-intro__stage" data-lib-stage>
            @if ($exterior)
                <div class="lib-intro__outside" data-lib-outside>
                    <x-picture :asset="$exterior" conversion="hero" sizes="100vw" eager alt="Enseigne « Bibliothèque — Université Adventiste Zurcher » sur le toit du bâtiment" />
                </div>
            @endif
            @if ($interior)
                <div class="lib-intro__inside" data-lib-inside aria-hidden="true">
                    <x-picture :asset="$interior" conversion="hero" sizes="100vw" decorative />
                </div>
            @endif
            <div class="lib-intro__shade" aria-hidden="true"></div>

            <div class="lib-intro__content container-site" data-lib-content>
                <x-breadcrumbs :items="$trail" dark />
                <p class="eyebrow mt-10 mb-5 text-sun-400!" data-lib-hide data-lib-fade>Vie étudiante · Université Adventiste Zurcher</p>
                <h1 id="lib-title" class="lib-title" data-lib-hide data-lib-title>Bibliothèque <em>Paul Pichot</em></h1>
                <p class="mt-6 max-w-xl text-lead text-white/85" data-lib-hide data-lib-fade>Un espace de savoir, de réflexion et d'inspiration.</p>
                <a href="#lib-quote" class="lib-cue" data-lib-hide data-lib-fade><span aria-hidden="true"></span>Entrez</a>
            </div>

            <p class="lib-intro__welcome" data-lib-welcome aria-hidden="true"><span data-lib-welcome-text>Bienvenue chez vous&nbsp;!</span></p>
        </div>
    </section>

    {{-- 2. LA CITATION : les mots s'allument au fil du défilement --}}
    <section id="lib-quote" class="lib-quote on-dark" data-lib-quote-section>
        <span class="lib-drift" data-lib-drift="-90" style="--h: 11rem; --left: 7%; --c: #7a2e3d" aria-hidden="true"></span>
        <span class="lib-drift" data-lib-drift="-160" style="--h: 15rem; --left: 13%; --c: #16204f" aria-hidden="true"></span>
        <span class="lib-drift" data-lib-drift="-60" style="--h: 9rem; --left: 88%; --c: #b0915a" aria-hidden="true"></span>
        <span class="lib-drift" data-lib-drift="-130" style="--h: 13rem; --left: 93%; --c: #8a5a35" aria-hidden="true"></span>
        <div class="container-site relative">
            <figure class="mx-auto max-w-5xl">
                <blockquote>
                    <p class="lib-quote__text" data-lib-quote>“Les bibliothèques sont des réservoirs de force, de grâce et d'intelligence… des lacs d'énergie mentale.”</p>
                </blockquote>
                <figcaption class="mt-10 flex items-center gap-4 text-sm tracking-[0.18em] text-sun-400 uppercase" data-reveal>
                    <span class="h-px w-12 bg-sun-400" aria-hidden="true"></span>Germaine Greer
                </figcaption>
            </figure>
        </div>
    </section>

    {{-- 3. LE LIEU : un panorama de la salle qu'on parcourt en défilant (épinglé et horizontal sur grand écran ; en pile sinon) --}}
    <section class="lib-pan" data-lib-pan aria-labelledby="lib-pan-title">
        <div class="lib-pan__sticky" data-lib-pan-sticky>
            <div class="lib-pan__head container-site">
                <p class="eyebrow">Un lieu vivant</p>
                <h2 id="lib-pan-title" class="lib-pan__title">Le cœur du savoir, au cœur du campus</h2>
            </div>
            <div class="lib-pan__track" data-lib-track>
                @if ($interior)
                    <div class="lib-pan__photo" data-lib-photo>
                        <x-picture :asset="$interior" conversion="hero" sizes="(min-width: 1024px) 170vw, 100vw" alt="Salle de la bibliothèque : rayonnages, grandes tables ovales et plafond en bois" />
                    </div>
                @endif
                <div class="lib-pan__cards">
                    @foreach ($pillars as [$name, $text])
                        <article class="lib-card" data-lib-card style="--n: {{ $loop->index }}">
                            <span class="lib-card__num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="lib-card__title">{{ $name }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="lib-pan__progress" aria-hidden="true"><span data-lib-bar></span></div>
        </div>
    </section>

    {{-- 4. LE RAYONNAGE : un livre à choisir pour découvrir ce que la bibliothèque propose --}}
    <section class="lib-shelf" data-lib-shelf aria-labelledby="lib-shelf-title">
        <div class="container-site">
            <header class="max-w-2xl">
                <p class="eyebrow" data-reveal>À votre disposition</p>
                <h2 id="lib-shelf-title" class="mt-4 text-h2 text-navy-900" data-reveal data-reveal-delay="0.1">Ce que vous y trouverez</h2>
                <p class="mt-5 text-lead text-ink-700" data-reveal data-reveal-delay="0.2" data-lib-shelf-hint>Une collection, des ressources en ligne, une équipe. Choisissez un livre sur l'étagère.</p>
            </header>

            <div class="lib-shelf__stage">
                <div class="lib-shelf__case">
                    <ul class="lib-shelf__books" role="list" data-lib-books>
                        @foreach ($books as $i => [$label, , , , $c1, $c2, $h])
                            <li>
                                <button type="button" class="lib-book" data-lib-book="{{ $i }}" aria-controls="lib-panel-{{ $i }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" style="--h: {{ $h }}rem; --c1: {{ $c1 }}; --c2: {{ $c2 }}">
                                    <span class="lib-book__label">{{ $label }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="lib-shelf__plank" aria-hidden="true"></div>
                </div>

                <div class="lib-shelf__panels" data-lib-panels aria-live="polite">
                    @foreach ($books as $i => [$label, $kicker, $text, $link])
                        <article id="lib-panel-{{ $i }}" class="lib-panel" data-lib-panel="{{ $i }}">
                            <p class="eyebrow">{{ $kicker }}</p>
                            <h3 class="lib-panel__title">{{ $label }}</h3>
                            <p class="lib-panel__text">{{ $text }}</p>
                            @if ($link)
                                <x-button :href="$link[1]" target="_blank" rel="noopener noreferrer" variant="navy" arrow class="btn-square mt-7">{{ $link[0] }}<span class="sr-only"> (nouvel onglet)</span></x-button>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 5. LES HORAIRES : tableau (affiche officielle) avec l'état « ouvert maintenant » calculé à l'heure de Madagascar --}}
    <section class="lib-hours" data-lib-hours aria-labelledby="lib-hours-title">
        <div class="container-site grid gap-12 lg:grid-cols-[1.15fr_0.85fr] lg:gap-20">
            <div>
                <p class="eyebrow" data-reveal>Horaires</p>
                <h2 id="lib-hours-title" class="mt-4 text-h2 text-navy-900" data-reveal data-reveal-delay="0.1">Quand venir&nbsp;?</h2>

                <p class="lib-status" data-lib-status data-state="unknown" role="status" aria-live="polite" data-reveal data-reveal-delay="0.2">
                    <span class="lib-status__dot" aria-hidden="true"></span><span data-lib-status-text>Consultez les horaires ci-dessous (heure de Madagascar).</span>
                </p>

                <div class="lib-tabs" role="tablist" aria-label="Type d'horaires" data-lib-tabs>
                    <button type="button" role="tab" id="lib-tab-open" aria-controls="lib-table-open" aria-selected="true" data-lib-tab="open">Ouverture</button>
                    <button type="button" role="tab" id="lib-tab-loan" aria-controls="lib-table-loan" aria-selected="false" tabindex="-1" data-lib-tab="loan">Prêt et prolongation</button>
                </div>

                @foreach (['open' => 'Horaires d\'ouverture', 'loan' => 'Prêt et prolongation de prêt'] as $key => $caption)
                    <div id="lib-table-{{ $key }}" class="lib-table-wrap" role="tabpanel" aria-labelledby="lib-tab-{{ $key }}" data-lib-table="{{ $key }}" data-reveal>
                        <h3 class="lib-table-title">{{ $caption }}</h3>
                        <table class="lib-table">
                            <caption class="sr-only">{{ $caption }} de la bibliothèque</caption>
                            <tbody>
                                @foreach ($schedule[$key] as $row)
                                    <tr data-days="{{ implode(',', $row['days']) }}" data-slots="{{ collect($row['slots'])->map(fn ($s) => $s[0].'-'.$s[1])->implode(',') }}">
                                        <th scope="row">{{ $row['label'] }}</th>
                                        <td>
                                            @if ($row['slots'])
                                                {{ collect($row['slots'])->map($range)->implode('  ·  ') }}
                                            @else
                                                <span class="text-ink-600 italic">{{ $row['note'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($key === 'loan')
                            <p class="lib-note">Le retour simple peut être effectué à tout moment durant toutes les heures d'ouverture. Toutefois, il est recommandé de le faire pendant les mêmes horaires que ceux prévus pour le prêt et la prolongation.</p>
                        @endif
                    </div>
                @endforeach

                <p class="mt-8 text-sm text-ink-600" data-reveal>Pour toute demande particulière ou requête spécifique, écrivez à <a href="mailto:uazlibrarydir@zurcher.edu.mg" class="font-semibold text-royal-600 underline underline-offset-4">uazlibrarydir@zurcher.edu.mg</a>.</p>
            </div>

            @if ($hoursPoster)
                <a href="{{ $hoursPoster->absoluteUrl() }}" target="_blank" rel="noopener noreferrer" class="lib-poster" data-lib-tilt data-reveal data-reveal-delay="0.15">
                    <span class="lib-poster__sheet" data-lib-tilt-sheet>
                        <x-picture :asset="$hoursPoster" conversion="card" sizes="(min-width: 1024px) 28rem, 90vw" alt="Affiche officielle des horaires d'ouverture et de prêt de la bibliothèque" />
                    </span>
                    <span class="lib-poster__cta">Voir l'affiche en grand <x-ico name="arrow-up-right" class="size-4" /><span class="sr-only"> (nouvel onglet)</span></span>
                </a>
            @endif
        </div>
    </section>

    {{-- 5 bis. LE SITE DE LA BIBLIOTHÈQUE : le site n'autorise pas l'affichage dans une autre page (X-Frame-Options), on le met donc en vitrine avec un lien --}}
    <section class="lib-online" data-lib-online aria-labelledby="lib-online-title">
        <div class="container-site grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
            <div>
                <p class="eyebrow" data-reveal>En ligne</p>
                <h2 id="lib-online-title" class="mt-4 text-h2 text-navy-900" data-reveal data-reveal-delay="0.1">Le site de la bibliothèque</h2>
                <p class="mt-5 max-w-xl text-lead text-ink-700" data-reveal data-reveal-delay="0.2">La bibliothèque a aussi son propre site, à découvrir à tout moment&nbsp;: <strong class="font-semibold text-navy-900">bibliotheque.zurcher.edu.mg</strong>.</p>
                <div class="mt-9" data-reveal data-reveal-delay="0.3">
                    <x-button href="https://bibliotheque.zurcher.edu.mg" target="_blank" rel="noopener noreferrer" variant="navy" arrow class="btn-square">Visiter le site<span class="sr-only"> bibliotheque.zurcher.edu.mg (nouvel onglet)</span></x-button>
                </div>
            </div>

            <a href="https://bibliotheque.zurcher.edu.mg" target="_blank" rel="noopener noreferrer" class="lib-window" data-lib-tilt data-reveal data-reveal-delay="0.15" aria-label="Ouvrir le site de la bibliothèque, bibliotheque.zurcher.edu.mg (nouvel onglet)">
                <span class="lib-window__sheet" data-lib-tilt-sheet aria-hidden="true">
                    <span class="lib-window__bar">
                        <i></i><i></i><i></i>
                        <span class="lib-window__url">bibliotheque.zurcher.edu.mg</span>
                    </span>
                    <span class="lib-window__body">
                        <span class="lib-window__shelf">
                            @foreach ([['#7a2e3d', 62], ['#16204f', 48], ['#b0915a', 74], ['#2f5d62', 55], ['#8a5a35', 68], ['#7a2e3d', 44], ['#16204f', 70], ['#b0915a', 52]] as [$color, $height])
                                <b style="--c: {{ $color }}; --h: {{ $height }}%"></b>
                            @endforeach
                        </span>
                        <span class="lib-window__go">Ouvrir le site <x-ico name="arrow-up-right" class="size-4" /></span>
                    </span>
                </span>
            </a>
        </div>
    </section>

    {{-- 6. BIENVENUE CHEZ VOUS : contact et livres numériques --}}
    <section class="lib-contact on-dark" data-lib-contact aria-labelledby="lib-contact-title">
        <div class="container-site grid items-center gap-14 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20">
            <div>
                <h2 id="lib-contact-title" class="lib-big" data-lib-big>Bienvenue chez vous&nbsp;!</h2>
                <p class="mt-8 max-w-xl text-lead text-white/85" data-reveal>La bibliothèque Paul Pichot n'est pas seulement un lieu où l'on conserve des livres : c'est un centre vivant d'apprentissage, un point de rencontre entre la foi, la connaissance et le service.</p>
                <div class="mt-10 flex flex-wrap gap-4" data-reveal data-reveal-delay="0.1">
                    <x-button href="http://ebookcentral.proquest.com/lib/zurcheradve/home.action" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square">Parcourir les ebooks<span class="sr-only"> (nouvel onglet)</span></x-button>
                    <x-button href="mailto:uazlibrarydir@zurcher.edu.mg" variant="outline" class="btn-square">Écrire à la bibliothèque</x-button>
                </div>
                <address class="mt-12 space-y-3 text-sm not-italic text-navy-100" data-reveal>
                    <p class="flex gap-3"><x-ico name="map-pin" class="mt-0.5 size-4 shrink-0 text-gold-300" />Université Adventiste Zurcher, Vohitsoa – PK 136 RN7 – BP 325, Antsirabe 110, Madagascar</p>
                    <p class="flex gap-3"><x-ico name="phone" class="mt-0.5 size-4 shrink-0 text-gold-300" /><a href="tel:+261340201906" class="hover:text-white">+261 34 02 019 06</a></p>
                    <p class="flex gap-3"><x-ico name="mail" class="mt-0.5 size-4 shrink-0 text-gold-300" /><a href="mailto:uazlibrarydir@zurcher.edu.mg" class="hover:text-white">uazlibrarydir@zurcher.edu.mg</a></p>
                    <p class="flex gap-3"><x-ico name="arrow-up-right" class="mt-0.5 size-4 shrink-0 text-gold-300" /><a href="https://bibliotheque.zurcher.edu.mg" target="_blank" rel="noopener noreferrer" class="hover:text-white">bibliotheque.zurcher.edu.mg<span class="sr-only"> (nouvel onglet)</span></a></p>
                </address>
            </div>

            @if ($exterior)
                <div class="lib-frame" data-lib-frame>
                    <div class="lib-frame__img" data-lib-frame-img>
                        <x-picture :asset="$exterior" conversion="hero" sizes="(min-width: 1024px) 34rem, 90vw" alt="Enseigne de la bibliothèque de l'Université Adventiste Zurcher" />
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
