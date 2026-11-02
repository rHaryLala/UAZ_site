@php
    $site = \App\Models\SiteSetting::current();
    $donation = config('uaz.donation_url');
    $contact = url('/contact?sujet='.rawurlencode('Soutenir l\'UAZ'));
    $contacts = config('uaz.donation_contacts');

    // Le mur de briques de la scène 2 : une colonne par entrée, sa hauteur en briques. Il monte avec la phrase (décor, aucun texte).
    $wall = [2, 3, 3, 4, 5, 5, 6, 7, 7, 8, 9, 10];

    // Montants des projets : on lit le premier nombre du « budget » (texte libre, ex. « 275 000 € ») ; les barres ne se dessinent que si
    // au moins deux projets ont un montant dans la même unité. Aucun total, aucune somme « déjà réunie » : seuls les montants publiés.
    $amounts = $projects->map(function ($project) {
        $text = (string) $project->budget;
        $number = preg_match('/\d(?:[\d\s\x{a0}\x{202f}.,]*\d)?/u', $text, $found) ? (int) preg_replace('/\D/', '', $found[0]) : 0;
        $unit = trim(preg_replace('/\d(?:[\d\s\x{a0}\x{202f}.,]*\d)?/u', '', $text));

        return ['project' => $project, 'amount' => $number, 'unit' => $unit];
    });
    $measured = $amounts->filter(fn ($a) => $a['amount'] > 0);
    $showBars = $measured->count() >= 2 && $measured->pluck('unit')->unique()->count() === 1;
    $maxAmount = max(1, (int) $measured->max('amount'));

    // Deux « plans » dessinés pour les projets sans photo : un long bâtiment (dortoir) et un groupe de maisons.
    $blueprints = [
        '<path d="M20 150 H380 M30 150 V80 H370 V150 M20 80 L200 40 L380 80" /><path d="M50 100 h28 v24 h-28z M95 100 h28 v24 h-28z M140 100 h28 v24 h-28z M262 100 h28 v24 h-28z M307 100 h28 v24 h-28z" /><path d="M180 150 v-34 h40 v34" /><path d="M200 40 V22 M190 22 h20" />',
        '<path d="M10 150 H390" /><path d="M30 150 V105 H110 V150 M22 105 L70 70 L118 105" /><path d="M150 150 V95 H235 V150 M142 95 L192 58 L243 95" /><path d="M275 150 V110 H360 V150 M267 110 L317 75 L368 110" /><path d="M48 118 h22 v18 h-22z M170 108 h24 v20 h-24z M295 122 h24 v14 h-24z" />',
    ];
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. LE CHANTIER : la photo respire, le titre se lève mot à mot (animations : resources/js/animations/support.js) --}}
    <header class="sup-hero on-dark" data-support data-sup-hero aria-labelledby="sup-title">
        @if ($page->cover)
            <div class="sup-hero__photo" data-sup-photo aria-hidden="true">
                <x-picture :asset="$page->cover" conversion="hero" sizes="100vw" decorative eager class="size-full object-cover object-[50%_35%]" />
            </div>
        @endif
        <div class="sup-hero__shade" aria-hidden="true"></div>

        <div class="container-site">
            <x-breadcrumbs :items="[['label' => $page->title]]" dark />
        </div>

        <div class="container-site sup-hero__body">
            <p class="eyebrow mb-5 text-sun-400!" data-sup-hide data-sup-fade>{{ __('support.hero.eyebrow') }}</p>
            <h1 id="sup-title" class="sup-title" data-sup-title>{{ $page->title }}</h1>
            <p class="sup-lead" data-sup-hide data-sup-fade>{{ $page->summary ?: __('support.hero.lead') }}</p>
            <div class="mt-9 flex flex-wrap gap-4" data-sup-hide data-sup-fade>
                @if ($donation)
                    <x-button :href="$donation" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square">{{ __('support.cta.donate') }}</x-button>
                @else
                    <x-button :href="$contact" variant="sun" arrow class="btn-square">{{ __('support.cta.contact') }}</x-button>
                @endif
                <x-button href="#projets" variant="outline">{{ __('support.cta.projects') }}</x-button>
            </div>
        </div>
    </header>

    {{-- 2. LA PREMIÈRE PIERRE : écran épinglé, la phrase s'allume mot à mot pendant que le mur monte brique après brique --}}
    <section class="sup-statement on-dark" data-sup-statement aria-labelledby="sup-statement-title">
        <div class="container-site sup-statement__inner">
            <div>
                <p class="eyebrow mb-8 text-sun-400!" id="sup-statement-title">{{ __('support.intro.eyebrow') }}</p>
                <p class="sup-quote" data-sup-quote>{{ __('support.intro.text') }}</p>
            </div>
            <div class="sup-wall" data-sup-wall aria-hidden="true">
                @foreach ($wall as $col => $height)
                    <div class="sup-wall__col">
                        @foreach (range(0, $height - 1) as $row)
                            <i @class(['sup-brick', 'is-gold' => ($col + $row) % 5 === 0]) data-sup-brick data-row="{{ $row }}" data-col="{{ $col }}"></i>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. LES PLANS : un projet, une planche — budget compté à l'arrivée, plan dessiné quand il n'y a pas de photo --}}
    <section id="projets" class="sup-projects" data-sup-projects aria-labelledby="sup-projects-title">
        <div class="container-site">
            <p class="eyebrow mb-4" data-reveal>{{ __('support.projects.eyebrow') }}</p>
            <h2 id="sup-projects-title" class="sup-h2" data-reveal>{{ __('support.projects.title') }}</h2>
            <p class="sup-projects__lead" data-reveal>{{ __('support.projects.lead') }}</p>

            @forelse ($projects as $project)
                <article class="sup-sheet {{ $loop->even ? 'is-flip' : '' }}" data-sup-sheet>
                    <div class="sup-sheet__media">
                        @if ($project->cover)
                            <x-picture :asset="$project->cover" conversion="hero" sizes="(min-width: 1024px) 50vw, 100vw" decorative class="size-full object-cover" />
                        @else
                            <svg class="sup-plan" viewBox="0 0 400 180" preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false" data-sup-plan>{!! $blueprints[$loop->index % 2] !!}</svg>
                        @endif
                        @if ($project->status_label)
                            <span class="sup-sheet__ribbon">{{ $project->status_label }}</span>
                        @endif
                    </div>

                    <div class="sup-sheet__body">
                        <p class="sup-sheet__num" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                        <p class="eyebrow mb-3">{{ __('Projet :str_pad_left', ['str_pad_left' => str_pad($loop->iteration, 2, '0', STR_PAD_LEFT)]) }}</p>
                        <h3 class="sup-sheet__title"><a href="{{ $project->url() }}" class="stretched-link">{{ $project->title }}</a></h3>
                        @if ($project->summary)<p class="sup-sheet__text">{{ $project->summary }}</p>@endif

                        @if ($project->budget || $project->location)
                            <dl class="sup-sheet__facts">
                                @if ($project->budget)<div><dt>{{ __('Budget prévu') }}</dt><dd class="sup-sheet__amount" data-sup-amount>{{ $project->budget }}</dd></div>@endif
                                @if ($project->location)<div><dt>{{ __('Lieu') }}</dt><dd>{{ $project->location }}</dd></div>@endif
                            </dl>
                        @endif

                        <span class="sup-sheet__more" aria-hidden="true">{{ __('support.projects.more') }} <x-ico name="arrow-right" class="size-4" /></span>
                    </div>
                </article>
            @empty
                <div class="sup-empty" data-reveal>
                    <p>{{ __('support.projects.empty') }}</p>
                </div>
            @endforelse

            @if ($showBars)
                <div class="sup-bars" data-sup-bars>
                    <h3 class="eyebrow mb-6" data-reveal>{{ __('Budget prévu') }}</h3>
                    <ul>
                        @foreach ($measured as $row)
                            <li class="sup-bar">
                                <span class="sup-bar__label">{{ $row['project']->title }}</span>
                                <span class="sup-bar__track" aria-hidden="true"><span class="sup-bar__fill" data-sup-fill style="--w: {{ round(100 * $row['amount'] / $maxAmount, 1) }}%"></span></span>
                                <span class="sup-bar__value">{{ $row['project']->budget }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    {{-- 4. COMMENT DONNER : deux portes, en ligne et par contact --}}
    <section class="sup-how on-dark" aria-labelledby="sup-how-title">
        <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(80rem,150vw)] -translate-x-1/2 -translate-y-1/2 text-gold-300/10" />
        <div class="container-site">
            <div class="mx-auto max-w-3xl text-center">
                <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ __('support.how.eyebrow') }}</p>
                <h2 id="sup-how-title" class="sup-h2 text-white!" data-reveal data-reveal-delay="0.1">{{ __('support.how.title') }}</h2>
            </div>

            <ul class="sup-doors">
                <li class="sup-door" data-reveal>
                    <x-ico name="heart" class="size-8 text-sun-400" />
                    <h3>{{ __('support.how.online.title') }}</h3>
                    @if ($donation)
                        <p>{{ __('support.how.online.text') }}</p>
                        <x-button :href="$donation" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square mt-8 w-fit">{{ __('support.cta.donate') }}</x-button>
                    @else
                        <p>{{ __('support.how.online.soon') }}</p>
                        <span class="sup-door__badge">{{ __('support.how.online.badge') }}</span>
                    @endif
                </li>
                <li class="sup-door" data-reveal data-reveal-delay="0.1">
                    <x-ico name="mail" class="size-8 text-sun-400" />
                    <h3>{{ __('support.how.contact.title') }}</h3>
                    <p>{{ __('support.how.contact.text') }}</p>
                    <ul class="sup-door__list">
                        @foreach ($contacts['phones'] ?? [] as $phone)
                            <li><x-ico name="phone" class="size-4 text-sun-400" /><a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}">{{ $phone }}</a></li>
                        @endforeach
                        @foreach ($contacts['emails'] ?? [] as $mail)
                            <li><x-ico name="mail" class="size-4 text-sun-400" /><a href="mailto:{{ $mail }}" class="break-all">{{ $mail }}</a></li>
                        @endforeach
                    </ul>
                    <x-button :href="$contact" variant="outline" arrow class="btn-square mt-8 w-fit">{{ __('support.cta.contact') }}</x-button>
                </li>
            </ul>
        </div>
    </section>

    {{-- 5. L'INVITATION --}}
    <section class="sup-invite on-dark" data-sup-invite aria-labelledby="sup-invite-title">
        <div class="container-site">
            <h2 id="sup-invite-title" class="sup-invite__text" data-sup-invite-text>{{ __('support.invite.text') }}</h2>
            <p class="mt-10" data-reveal>
                <x-button :href="$donation ?: $contact" :target="$donation ? '_blank' : null" :rel="$donation ? 'noopener noreferrer' : null" variant="sun" arrow class="btn-square">{{ $donation ? __('support.cta.donate') : __('support.cta.contact') }}</x-button>
            </p>
        </div>
    </section>
</x-layouts.app>
