@php
    // Faits du Manuel de l'étudiant (section 3.3) : une équipe de candidature compte « au plus 15 noms ».
    $team = 15;

    // Le demi-cercle des quinze sièges (SVG 640 × 430, centre en bas) : de gauche à droite, du premier au quinzième nom.
    $cx = 320;
    $cy = 400;
    $radius = 270;
    $seats = collect(range(0, $team - 1))->map(fn (int $i) => [
        'x' => round($cx + $radius * cos(M_PI - $i * M_PI / ($team - 1)), 1),
        'y' => round($cy - $radius * sin(M_PI - $i * M_PI / ($team - 1)), 1),
    ]);

    // La foule des étudiants inscrits : 6 rangées de 16 points ; quelques-uns, répartis, sont les élus (décor, aucun chiffre n'est annoncé).
    $crowd = 96;
    $elected = [5, 19, 26, 38, 53, 62, 77, 90];

    $steps = ['team', 'call', 'interview'];
    $principles = ['promote', 'discuss', 'prioritize'];

    // La planification : barres (position et largeur en % de la ligne) ; les projets du VRAE sont en tête.
    $vraeBars = [['x' => 6, 'w' => 34], ['x' => 46, 'w' => 22], ['x' => 72, 'w' => 24]];
    $otherBars = [
        [['x' => 2, 'w' => 18], ['x' => 24, 'w' => 28], ['x' => 58, 'w' => 16], ['x' => 78, 'w' => 18]],
        [['x' => 10, 'w' => 26], ['x' => 42, 'w' => 14], ['x' => 62, 'w' => 30]],
    ];
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. « SA » : deux lettres géantes remplies d'une vraie photo du campus (animations : resources/js/animations/association.js) --}}
    <header class="sa-hero on-dark" data-association data-sa-hero aria-labelledby="sa-title" @if ($photo) style="--sa-photo: url('{{ $photo->url('card') }}')" @endif>
        <div class="sa-hero__grid" aria-hidden="true"></div>

        <div class="container-site">
            <x-breadcrumbs :items="$trail" dark />
        </div>

        <div class="container-site sa-hero__body">
            <div class="sa-hero__text">
                <p class="eyebrow mb-5 text-sun-400!" data-sa-hide data-sa-fade>{{ __('association.hero.eyebrow') }}</p>
                <h1 id="sa-title" class="sa-title" data-sa-title>{{ $page->title }}</h1>
                <p class="sa-lead" data-sa-hide data-sa-fade>{{ __('association.hero.lead') }}</p>
                <a href="#role" class="sa-cue" data-sa-hide data-sa-fade><span aria-hidden="true"></span>{{ __('association.hero.cue') }}</a>
            </div>

            <div class="sa-letters" aria-hidden="true" data-sa-letters>
                <span class="sa-letter" data-sa-letter>S</span>
                <span class="sa-letter" data-sa-letter>A</span>
            </div>
        </div>
    </header>

    {{-- 2. SON RÔLE : la phrase s'allume mot à mot, puis la chaîne VRAE → SA → activités se trace --}}
    <section id="role" class="sa-role" data-sa-role aria-labelledby="sa-role-title">
        <div class="container-site sa-role__inner">
            <div>
                <p class="eyebrow mb-5 text-gold-700">{{ __('association.role.eyebrow') }}</p>
                <h2 id="sa-role-title" class="sa-statement" data-sa-statement="0.62">{{ __('association.role.statement') }}</h2>
            </div>

            <ol class="sa-chain" data-sa-chain>
                <li class="sa-node" data-sa-node>
                    <span class="sa-node__abbr" aria-hidden="true">{{ __('association.candidate.vrae_short') }}</span>
                    <span class="sa-node__name">{{ __('association.role.vrae') }}</span>
                </li>
                <li class="sa-link" data-sa-link><span class="sa-link__line" aria-hidden="true"></span><span class="sa-link__label">{{ __('association.role.vrae_link') }}</span></li>
                <li class="sa-node sa-node--sa" data-sa-node>
                    <span class="sa-node__abbr" aria-hidden="true">SA</span>
                    <span class="sa-node__name">{{ __('association.role.sa') }}</span>
                </li>
                <li class="sa-link" data-sa-link><span class="sa-link__line" aria-hidden="true"></span><span class="sa-link__label">{{ __('association.role.sa_link') }}</span></li>
                <li class="sa-node sa-node--plain" data-sa-node>
                    <span class="sa-node__name">{{ __('association.role.activities') }}</span>
                </li>
            </ol>
        </div>
    </section>

    {{-- 3. SES OFFICIANTS : tous les étudiants inscrits votent, quelques-uns sont élus --}}
    <section class="sa-elected on-dark" data-sa-elected aria-labelledby="sa-elected-title">
        <div class="container-site sa-elected__inner">
            <div>
                <p class="eyebrow mb-5 text-sun-400!">{{ __('association.elected.eyebrow') }}</p>
                <h2 id="sa-elected-title" class="sa-statement sa-statement--light" data-sa-statement="0.5">{{ __('association.elected.statement') }}</h2>
                <p class="sa-caption">{{ __('association.elected.caption') }}</p>
            </div>

            <div class="sa-crowd" data-sa-crowd aria-hidden="true">
                @foreach (range(0, $crowd - 1) as $i)
                    <i @class(['is-elected' => in_array($i, $elected, true)])></i>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 4. SE PRÉSENTER : quinze sièges, l'appel à candidature, puis l'entretien avec le VRAE ; écran arrêté sur grand écran --}}
    <section id="candidature" class="sa-path on-dark" data-sa-path aria-labelledby="sa-path-title">
        <div class="sa-path__stage" data-sa-stage>
            <div class="container-site sa-path__inner">
                <div class="sa-path__text">
                    <p class="eyebrow mb-4 text-sun-400!">{{ __('association.candidate.eyebrow') }}</p>
                    <h2 id="sa-path-title" class="sa-h2">{{ __('association.candidate.title') }}</h2>

                    <ol class="sa-steps">
                        @foreach ($steps as $key)
                            <li class="sa-step" data-sa-step>
                                <p class="sa-step__kicker">{{ __('association.candidate.kicker', ['n' => $loop->iteration]) }}</p>
                                <h3 class="sa-step__title">{{ __('association.candidate.steps.'.$key.'.title') }}</h3>
                                <p class="sa-step__text">{{ __('association.candidate.steps.'.$key.'.text') }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <figure class="sa-hemi" data-sa-hemi>
                    <svg viewBox="0 0 640 430" role="img" aria-label="{{ __('association.candidate.visual') }}">
                        <path class="sa-hemi__arc" d="M50 400 A270 270 0 0 1 590 400" data-sa-arc />
                        <g class="sa-hemi__rings" data-sa-rings>
                            @foreach ([60, 60, 60] as $ring)
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $ring }}" />
                            @endforeach
                        </g>
                        @foreach ($seats as $seat)
                            <circle class="sa-seat" cx="{{ $seat['x'] }}" cy="{{ $seat['y'] }}" r="17" data-sa-seat />
                        @endforeach
                        <text class="sa-hemi__num" x="{{ $cx }}" y="378" text-anchor="middle" data-sa-num data-final="{{ $team }}">{{ $team }}</text>
                        <text class="sa-hemi__cap" x="{{ $cx }}" y="410" text-anchor="middle" data-sa-cap="1">{{ __('association.candidate.max_label') }}</text>
                        <text class="sa-hemi__cap sa-hemi__cap--alt" x="{{ $cx }}" y="410" text-anchor="middle" data-sa-cap="2">{{ __('association.candidate.call_label') }}</text>
                        <text class="sa-hemi__cap sa-hemi__cap--alt" x="{{ $cx }}" y="410" text-anchor="middle" data-sa-cap="3">{{ __('association.candidate.interview_label') }}</text>
                        <path class="sa-hemi__link" d="M320 70 V108" data-sa-vlink />
                        <g class="sa-vrae" data-sa-vrae>
                            <rect x="225" y="14" width="190" height="56" rx="3" />
                            <text x="{{ $cx }}" y="50" text-anchor="middle">{{ __('association.candidate.vrae_short') }}</text>
                        </g>
                    </svg>
                </figure>
            </div>
        </div>
    </section>

    {{-- 5. SON ENGAGEMENT : les projets du VRAE passent en tête de la planification --}}
    <section id="engagement" class="sa-eng" data-sa-eng aria-labelledby="sa-eng-title">
        <div class="container-site">
            <div class="sa-eng__head">
                <p class="eyebrow mb-5 text-gold-700">{{ __('association.engagement.eyebrow') }}</p>
                <h2 id="sa-eng-title" class="sa-h2 sa-h2--ink">{{ __('association.engagement.title') }}</h2>
                <p class="sa-eng__lead">{{ __('association.engagement.lead') }}</p>
            </div>

            <figure class="sa-plan" data-sa-plan>
                <figcaption class="sa-plan__title">{{ __('association.engagement.plan') }}</figcaption>
                <div role="img" aria-label="{{ __('association.engagement.visual') }}">
                    <div class="sa-lane sa-lane--vrae" data-sa-lane="vrae">
                        <span class="sa-lane__label">{{ __('association.engagement.lane_vrae') }}</span>
                        <div class="sa-lane__track">
                            @foreach ($vraeBars as $bar)
                                <i class="sa-bar sa-bar--vrae" data-sa-bar="vrae" style="--x: {{ $bar['x'] }}%; --w: {{ $bar['w'] }}%"></i>
                            @endforeach
                        </div>
                    </div>
                    <div class="sa-lane sa-lane--others" data-sa-lane="others">
                        <span class="sa-lane__label">{{ __('association.engagement.lane_others') }}</span>
                        <div class="sa-lane__tracks">
                            @foreach ($otherBars as $track)
                                <div class="sa-lane__track">
                                    @foreach ($track as $bar)
                                        <i class="sa-bar" data-sa-bar="other" style="--x: {{ $bar['x'] }}%; --w: {{ $bar['w'] }}%"></i>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </figure>

            <ul class="sa-principles">
                @foreach ($principles as $key)
                    <li class="sa-principle" data-sa-principle>
                        <p class="sa-principle__word">{{ __('association.engagement.principles.'.$key.'.word') }}</p>
                        <p class="sa-principle__text">{{ __('association.engagement.principles.'.$key.'.text') }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 6. LA SOURCE : le manuel de l'étudiant, et les autres pages de la vie étudiante --}}
    <section class="sa-end on-dark" aria-labelledby="sa-end-title">
        <div class="container-site sa-end__inner">
            <div>
                <p class="eyebrow mb-5 text-sun-400!">{{ __('association.closing.eyebrow') }}</p>
                <h2 id="sa-end-title" class="sa-h2">{{ __('association.closing.title') }}</h2>
                <p class="sa-end__text">{{ __('association.closing.text') }}</p>
                @if ($downloads)
                    <p class="mt-8"><x-button :href="$downloads->url()" variant="gold" arrow class="btn-square">{{ __('association.closing.manual') }}</x-button></p>
                @endif
                <p class="sa-end__source">{{ __('association.sources') }}</p>
            </div>

            @if ($siblings->isNotEmpty())
                <nav class="sa-more" aria-label="{{ __('association.closing.more') }}">
                    <p class="sa-more__title">{{ __('association.closing.more') }}</p>
                    <ul>
                        @foreach ($siblings as $sibling)
                            <li><a href="{{ $sibling->url() }}"><span>{{ $sibling->title }}</span><x-ico name="arrow-up-right" class="size-5" /></a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </section>
</x-layouts.app>
