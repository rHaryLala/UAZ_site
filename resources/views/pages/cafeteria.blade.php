@php
    // Trois soulignements dessinés « à la main » (tracés par GSAP au défilement) : un par nourriture de la citation.
    $underline = '<svg class="caf-stroke" viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M3 9 C 40 2, 78 14, 116 6 S 180 11, 197 4" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>';
    $circle = '<svg class="caf-loop" viewBox="0 0 200 80" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M12 44 C 8 14, 188 4, 193 38 C 197 70, 24 78, 14 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>';

    $nourishments = [
        [__('nourrir le corps,'), '#f0b44c'],
        [__('d\'élever l\'esprit'), '#ff8a5c'],
        [__('et de renforcer la communion.'), '#8fd3f4'],
    ];
    $diet = [__('Végétarienne'), __('Saine'), __('Naturelle')];
    $ingredients = [__('Frais'), __('Locaux'), __('Nutritifs')];

    // Le triptyque : chaque volet reprend une phrase du texte de présentation.
    $panels = [
        [__('Se retrouver'), __('Un lieu de rencontre et d\'échange, où étudiants, enseignants et visiteurs se retrouvent.'), $service, __('Des personnes se servent au buffet de la cafétéria')],
        [__('Préparer'), __('Des plats savoureux, équilibrés et adaptés à un mode de vie sain.'), $kitchen, __('Un cuisinier prépare un plat à la poêle dans la cuisine de la cafétéria')],
        [__('L\'équipe'), __('Une équipe qui œuvre chaque jour pour vous accueillir.'), $team, __('L\'équipe de la cafétéria en tenue de cuisine devant le bâtiment')],
    ];
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. L'ASSIETTE : le titre se sert lettre à lettre, l'assiette tourne autour de sa couronne de mots (animations : resources/js/animations/cafeteria.js) --}}
    <section class="caf-hero" data-cafeteria data-caf-hero aria-labelledby="caf-title">
        <div class="container-site">
            <x-breadcrumbs :items="$trail" />
        </div>
        <div class="container-site caf-hero__grid">
            <div class="caf-hero__text">
                <h1 id="caf-title" class="caf-title" data-caf-title>{{ __('Cafétéria') }}</h1>
                <svg class="caf-title__stroke" viewBox="0 0 400 20" preserveAspectRatio="none" aria-hidden="true" focusable="false" data-caf-stroke><path d="M4 12 C 60 2, 110 20, 170 9 S 300 16, 396 5" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>
                <p class="caf-hero__lead" data-caf-hide data-caf-fade>{{ __('Une expérience culinaire simple, équilibrée et inspirante.') }}</p>
                @if ($menus->isNotEmpty())
                    <p class="mt-8" data-caf-hide data-caf-fade><x-button href="#menu" variant="navy" arrow>{{ __('Voir le menu de la semaine') }}</x-button></p>
                @endif
            </div>

            <div class="caf-plate" data-caf-plate>
                <svg class="caf-plate__ring" viewBox="0 0 200 200" aria-hidden="true" focusable="false" data-caf-ring>
                    <defs><path id="caf-ring-path" d="M100,100 m-86,0 a86,86 0 1,1 172,0 a86,86 0 1,1 -172,0"/></defs>
                    <text><textPath href="#caf-ring-path" textLength="535" lengthAdjust="spacing">Santé · Bien-être · Convivialité · Santé · Bien-être · Convivialité ·</textPath></text>
                </svg>
                @if ($service)
                    <div class="caf-plate__photo" data-caf-plate-photo>
                        <x-picture :asset="$service" conversion="hero" sizes="(min-width: 1024px) 34rem, 80vw" eager :alt="__('Des personnes se servent au buffet de la cafétéria')" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- 2. TROIS NOURRITURES : l'écran reste en place, la citation se révèle phrase après phrase et la couleur du fond change --}}
    <section class="caf-nourish on-dark" data-caf-nourish aria-label="{{ __('Citation') }}">
        <div class="caf-nourish__stage" data-caf-nourish-stage>
            <blockquote class="container-site caf-nourish__quote">
                <p>
                    <span class="caf-nourish__lead" data-caf-lead>{{ __('La nourriture est bien plus qu\'un besoin : elle est un moyen de') }}</span>
                    @foreach ($nourishments as [$phrase, $color])
                        <span class="caf-phrase" data-caf-phrase style="--accent: {{ $color }}"><span class="caf-phrase__text">{!! e($phrase) !!}</span>{!! $underline !!}</span>
                    @endforeach
                </p>
            </blockquote>
        </div>
    </section>

    {{-- 3. À L'ASSIETTE : les six mots du texte, entourés au fil de l'eau --}}
    <section class="caf-plate-section" data-caf-principles aria-labelledby="caf-principles-title">
        <div class="container-site">
            <div class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:gap-20">
                <div>
                    <h2 id="caf-principles-title" class="caf-h2" data-reveal>{{ __('Préparé dans l\'esprit des principes adventistes') }}</h2>
                    <p class="mt-6 max-w-md text-lead text-ink-700" data-reveal data-reveal-delay="0.1">{{ __('Pour soutenir votre énergie, votre concentration et votre croissance spirituelle.') }}</p>
                </div>

                <div class="grid gap-10 sm:grid-cols-2">
                    @foreach ([[__('Une alimentation'), $diet], [__('Des ingrédients'), $ingredients]] as [$legend, $words])
                        <div>
                            <p class="eyebrow mb-5" data-reveal>{{ $legend }}</p>
                            <ul class="caf-tiles">
                                @foreach ($words as $word)
                                    <li class="caf-tile" data-caf-tile data-reveal data-reveal-delay="{{ 0.08 * $loop->iteration }}">
                                        <span>{{ $word }}</span>{!! $circle !!}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 4. AU MENU : un tableau par semaine (semaine en cours et suivantes), saisi dans le back-office (Menus de la cafétéria).
         Sans JavaScript : toutes les semaines sont empilées et le tableau défile ; avec : onglets par semaine et, sur petit écran, un jour à la fois (animations : cafeteria.js). --}}
    @if ($menus->isNotEmpty())
        @php($mealLabels = ['morning' => __('Matin'), 'noon' => __('Midi'), 'evening' => __('Soir')])
        <section id="menu" class="caf-menu" data-caf-menu aria-labelledby="caf-menu-title">
            <div class="container-site">
                <header class="caf-menu__head">
                    <div>
                        <p class="eyebrow mb-5" data-reveal>{{ __('Au menu') }}</p>
                        <h2 id="caf-menu-title" class="caf-h2" data-reveal>{{ __('Le menu de la semaine') }}</h2>
                    </div>
                    <p class="caf-menu__lead" data-reveal data-reveal-delay="0.1">{{ __('Matin, midi et soir : les repas servis à la cafétéria, semaine après semaine.') }}</p>
                </header>

                @if ($menus->count() > 1)
                    <div class="caf-weeks" role="tablist" aria-label="{{ __('Choisir la semaine') }}" data-caf-weeks>
                        @foreach ($menus as $menu)
                            <button type="button" role="tab" id="caf-week-tab-{{ $menu->id }}" class="caf-week" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="caf-week-{{ $menu->id }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-caf-week="{{ $menu->id }}">
                                <span class="caf-week__range">{{ $menu->shortRange() }}</span>
                                @if ($menu->isCurrent())<span class="caf-week__now">{{ __('En cours') }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                @endif

                @foreach ($menus as $menu)
                    @php($days = $menu->days())
                    @php($todayIndex = collect($days)->search(fn ($day) => $day['today']))
                    <div id="caf-week-{{ $menu->id }}" class="caf-menu__panel" data-caf-week-panel="{{ $menu->id }}" data-caf-today="{{ $todayIndex === false ? 0 : $todayIndex }}" @if ($menus->count() > 1) role="tabpanel" aria-labelledby="caf-week-tab-{{ $menu->id }}" @endif>
                        <h3 class="caf-menu__week" id="caf-week-title-{{ $menu->id }}">{{ $menu->rangeLabel() }}</h3>

                        <div class="caf-days" role="group" aria-label="{{ __('Choisir le jour') }}" data-caf-days>
                            @foreach ($days as $i => $day)
                                <button type="button" class="caf-day {{ $day['today'] ? 'is-today' : '' }}" aria-pressed="false" data-caf-chip="{{ $i }}">
                                    <span class="caf-day__abbr">{{ $day['abbr'] }}</span>
                                    <span class="caf-day__num">{{ $day['date']->format('j') }}</span>
                                    <span class="sr-only">{{ $day['name'] }} {{ $day['short'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="caf-table-wrap" role="region" aria-labelledby="caf-week-title-{{ $menu->id }}" tabindex="0">
                            <table class="caf-table">
                                <caption class="sr-only">{{ __('Menu de la cafétéria') }} — {{ $menu->rangeLabel() }}</caption>
                                <thead>
                                    <tr>
                                        <td class="caf-table__corner"><span class="sr-only">{{ __('Repas') }}</span></td>
                                        @foreach ($days as $i => $day)
                                            <th scope="col" class="caf-table__day {{ $day['today'] ? 'is-today' : '' }}" data-caf-day="{{ $i }}" @if ($day['today']) aria-current="date" @endif>
                                                <span class="caf-table__dow">{{ $day['name'] }}</span>
                                                <span class="caf-table__date">{{ $day['short'] }}</span>
                                                @if ($day['today'])<span class="caf-table__now">{{ __('Aujourd\'hui') }}</span>@endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($mealLabels as $meal => $label)
                                        <tr>
                                            <th scope="row" class="caf-table__meal"><span>{{ $label }}</span></th>
                                            @foreach ($days as $i => $day)
                                                <td class="{{ $day['today'] ? 'is-today' : '' }}" data-caf-day="{{ $i }}">
                                                    @if ($day['meals'][$meal])
                                                        <ul class="caf-dishes">
                                                            @foreach ($day['meals'][$meal] as $dish)
                                                                <li>{{ $dish }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <span aria-hidden="true" class="caf-dishes__none">—</span><span class="sr-only">{{ __('Non renseigné') }}</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if (filled($menu->note))
                            <p class="caf-menu__note">{{ $menu->note }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 5. LES COULISSES : un triptyque dont le volet choisi s'ouvre (clic, survol ou clavier) --}}
    <section class="caf-triptych-section" data-caf-triptych aria-labelledby="caf-triptych-title">
        <div class="container-site">
            <h2 id="caf-triptych-title" class="caf-h2" data-reveal>{{ __('Du buffet à la cuisine') }}</h2>
            <div class="caf-triptych" data-caf-panels>
                @foreach ($panels as $i => [$title, $text, $photo, $alt])
                    <article class="caf-panel {{ $i === 0 ? 'is-open' : '' }}" data-caf-panel>
                        @if ($photo)
                            <div class="caf-panel__img"><x-picture :asset="$photo" conversion="hero" sizes="(min-width: 1024px) 50vw, 100vw" :alt="$alt" /></div>
                        @endif
                        <div class="caf-panel__shade" aria-hidden="true"></div>
                        <h3 class="caf-panel__title"><button type="button" class="caf-panel__btn" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="caf-panel-{{ $i }}" data-caf-open><span class="caf-panel__num" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span><span class="caf-panel__label">{{ $title }}</span></button></h3>
                        <p id="caf-panel-{{ $i }}" class="caf-panel__text" data-caf-text>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 6. L'AMBIANCE : trois mots qui défilent ; le défilement de la page les accélère et les incline --}}
    <section class="caf-marquee-section" aria-labelledby="caf-marquee-title">
        <p class="sr-only" id="caf-marquee-title">{{ __('Paix, fraternité, gratitude') }}</p>
        <div class="caf-marquee" data-caf-marquee aria-hidden="true">
            <div class="caf-marquee__track" data-caf-track>
                @foreach (range(1, 4) as $copy)
                    <span class="caf-marquee__group">
                        @foreach ([__('Paix'), __('Fraternité'), __('Gratitude')] as $word)
                            <span class="caf-marquee__word {{ $loop->index === 1 ? 'is-outline' : '' }}">{{ $word }}</span><i>✦</i>
                        @endforeach
                    </span>
                @endforeach
            </div>
        </div>
        <p class="container-site mx-auto mt-12 max-w-2xl text-center font-serif text-xl leading-relaxed text-navy-900 md:text-2xl" data-reveal>{{ __('Une ambiance accueillante, calme, et propice au repos au milieu de vos journées d\'étude ou de travail.') }}</p>
    </section>

    {{-- 7. L'INVITATION --}}
    <section class="caf-invite on-dark" data-caf-invite aria-labelledby="caf-invite-title">
        <div class="container-site">
            <h2 id="caf-invite-title" class="caf-invite__text" data-caf-invite-text>{{ __('Chaque passage est une invitation à prendre soin de vous.') }}</h2>
            <p class="mt-12 flex items-center gap-4 text-sm tracking-[0.18em] text-sun-400 uppercase" data-reveal><span class="h-px w-12 bg-sun-400" aria-hidden="true"></span>{{ __('La Cafétéria · Université Adventiste Zurcher') }}</p>
        </div>
    </section>
</x-layouts.app>
