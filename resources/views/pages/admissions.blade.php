@php
    use Carbon\Carbon;

    $tz = 'Indian/Antananarivo';
    $today = now($tz)->startOfDay();
    $fr = fn (Carbon $d) => $d->copy()->locale('fr');

    // Calendrier : chaque date est « passée », « prochaine » ou « à venir » à la date du jour (heure de Madagascar).
    $milestones = collect($data['milestones'])->map(fn ($m) => $m + ['at' => Carbon::parse($m['date'], $tz)->startOfDay()]);
    $next = $milestones->first(fn ($m) => $m['at']->gte($today));
    $daysToNext = $next ? (int) $today->diffInDays($next['at']) : null;
    $milestones = $milestones->map(fn ($m) => $m + ['state' => $m['at']->lt($today) ? 'past' : ($next && $m['date'] === $next['date'] ? 'next' : 'future')]);
    $stateLabels = ['past' => __('Passé'), 'next' => __('Prochaine étape'), 'future' => __('À venir')];

    // Puces du héros : ce que l'on sait de la formation (durée et crédits viennent de la fiche des formations de ce niveau).
    $sample = $formations->first();
    $chips = collect([$data['mode']])
        ->when($sample?->duration, fn ($c, $v) => $c->push($v))
        ->when($sample?->credits, fn ($c, $v) => $c->push(__(':credits crédits', ['credits' => $v])))
        ->all();

    $docCount = count($data['documents']);
    $contact = $common['contact'];
    $registration = config('uaz.registration_url');
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
<div class="adm adm--{{ $level }}" data-admissions data-adm-level="{{ $level }}">
    {{-- 1. L'OUVERTURE : le titre se découpe en mots, le sceau tourne en défilant, la photo se dévoile (animations : resources/js/animations/admissions.js) --}}
    <header class="adm-hero" data-adm-hero aria-labelledby="adm-title">
        <img class="adm-hero__seal" data-adm-seal src="{{ asset('images/uaz-seal-768.webp') }}" alt="" width="768" height="768" decoding="async">
        <div class="container-site">
            <x-breadcrumbs :items="$trail" />
        </div>
        <div class="container-site adm-hero__grid">
            <div class="adm-hero__text">
                <p class="adm-badge" data-adm-hide data-adm-fade>{{ __('Admission :year', ['year' => $common['year']]) }}</p>
                <h1 id="adm-title" class="adm-title" data-adm-hide data-adm-title>{{ $data['title'] }}</h1>
                <ul class="adm-chips" data-adm-hide data-adm-fade>
                    @foreach ($chips as $chip)<li>{{ $chip }}</li>@endforeach
                </ul>
                @if ($next)
                    <p class="adm-next" data-adm-hide data-adm-fade>
                        {{ __(':label · :hint, dans', ['label' => $next['label'], 'hint' => strtolower($next['hint'])]) }} <strong data-adm-count="{{ $daysToNext }}">{{ $daysToNext }}</strong> {{ $daysToNext > 1 ? 'jours' : 'jour' }}
                    </p>
                @endif
                <div class="adm-hero__cta" data-adm-hide data-adm-fade>
                    <x-button :href="$registration" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square">{{ __('S\'inscrire') }}<span class="sr-only"> {{ __('(nouvel onglet)') }}</span></x-button>
                    <a href="#adm-dossier" class="link-arrow">{{ __('Voir le dossier') }}</a>
                </div>
            </div>

            @if ($page->cover)
                <div class="adm-hero__photo ornament" data-adm-photo>
                    <x-picture :asset="$page->cover" conversion="hero" sizes="(min-width: 1024px) 46vw, 92vw" eager />
                </div>
            @endif
        </div>
    </header>

    {{-- 2. LE CALENDRIER : un point avance sur le chemin des dates en défilant --}}
    <section class="adm-calendar" data-adm-calendar aria-labelledby="adm-cal-title">
        <div class="adm-calendar__stage" data-adm-cal-stage>
            <div class="container-site">
                <h2 id="adm-cal-title" class="adm-h2" data-reveal>{{ __('Le calendrier') }}</h2>

                <div class="adm-road" data-adm-road aria-hidden="true">
                    <svg viewBox="0 0 1000 200" preserveAspectRatio="none" focusable="false">
                        <path class="adm-road__ghost" d="M10 160 C 170 160, 230 50, 400 60 S 650 175, 810 110 S 940 55, 990 60" />
                        <path class="adm-road__path" data-adm-path d="M10 160 C 170 160, 230 50, 400 60 S 650 175, 810 110 S 940 55, 990 60" />
                    </svg>
                    @foreach ($milestones as $m)<span class="adm-road__node" data-adm-node></span>@endforeach
                    <span class="adm-road__dot" data-adm-dot></span>
                </div>

                <ol class="adm-miles" role="list">
                    @foreach ($milestones as $m)
                        <li class="adm-mile" data-adm-mile data-state="{{ $m['state'] }}">
                            <time datetime="{{ $m['date'] }}" class="adm-mile__date">
                                <span class="adm-mile__day">{{ $m['at']->day }}</span>
                                <span class="adm-mile__month">{{ $fr($m['at'])->isoFormat('MMMM YYYY') }}</span>
                            </time>
                            <p class="adm-mile__label">{{ $m['label'] }}</p>
                            <p class="adm-mile__hint">{{ $m['hint'] }}</p>
                            <span class="adm-mile__state">{{ $stateLabels[$m['state']] }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($data['schedule'])
                    <div class="adm-week" data-adm-week data-reveal>
                        <p class="adm-week__text">{{ __('Cours en ligne :text', ['text' => $data['schedule']['text']]) }}</p>
                        <ul class="adm-week__days" role="list">
                            @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $day)
                                <li class="{{ in_array($day, $data['schedule']['days'], true) ? 'is-on' : '' }}" data-adm-day><span>{{ $day }}</span>@if (in_array($day, $data['schedule']['days'], true))<small>{{ $data['schedule']['hours'] }}</small>@endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- 3. LE DOSSIER : la chemise s'ouvre, les pièces se cochent une à une --}}
    <section id="adm-dossier" class="adm-dossier" data-adm-dossier aria-labelledby="adm-doc-title">
        <div class="container-site adm-dossier__grid">
            <div class="adm-dossier__side">
                <div class="adm-folder" data-adm-folder aria-hidden="true">
                    <span class="adm-folder__tab"></span>
                    <span class="adm-folder__back"></span>
                    <span class="adm-folder__sheets"><i></i><i></i><i></i></span>
                    @if ($level === 'master')<span class="adm-scan" data-adm-scan></span>@endif
                    <span class="adm-folder__front" data-adm-flap><b>{{ $data['documents_title'] }}</b></span>
                    <span class="adm-stamp" data-adm-stamp>{{ __('Dossier complet') }}</span>
                </div>
                <p class="adm-counter" aria-live="off"><span data-adm-docs-count>0</span> {{ __('/ :doccount pièces', ['doccount' => $docCount]) }}</p>
            </div>

            <div class="adm-dossier__main">
                <h2 id="adm-doc-title" class="adm-h2" data-reveal>{{ __('Les pièces du dossier') }}</h2>
                <p class="adm-deposit" data-reveal>{{ $data['deposit'] }}</p>

                <ol class="adm-docs" data-adm-docs role="list">
                    @foreach ($data['documents'] as $doc)
                        <li class="adm-doc" data-adm-doc>
                            <svg class="adm-doc__check" viewBox="0 0 28 28" aria-hidden="true" focusable="false"><rect x="2" y="2" width="24" height="24" rx="4" fill="none" stroke="currentColor" stroke-width="2" /><path data-adm-tick d="M7 14.5 L12 19.5 L21 8.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <div>
                                <p>{{ $doc[0] }}</p>
                                @if (isset($doc[1]))<p class="adm-doc__note">{{ $doc[1] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($data['fee'])
                    <p class="adm-fee" data-reveal><strong>{{ $data['fee']['amount'] }}</strong><span>{{ $data['fee']['unit'] }}</span><em>{{ $data['fee']['text'] }}</em></p>
                @endif

                @if ($forms)
                    <ul class="adm-forms" role="list" data-reveal>
                        @foreach ($forms as [$label, $url])
                            <li><a href="{{ $url }}" class="adm-forms__link" target="_blank" rel="noopener noreferrer"><x-ico name="download" class="size-5" /><span>{{ $label }}</span><span class="sr-only"> {{ __('(téléchargement, nouvel onglet)') }}</span></a></li>
                        @endforeach
                    </ul>
                @endif

                @if ($data['after'])
                    <div class="adm-after" data-reveal>
                        <h3 class="adm-after__title">{{ __('Après le dépôt') }}</h3>
                        <ol role="list">
                            @foreach ($data['after'] as $step)<li><span aria-hidden="true">{{ $loop->iteration }}</span>{{ $step }}</li>@endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- 4. LES MENTIONS : une carte qu'on ouvre, les autres se réorganisent (GSAP Flip) --}}
    <section class="adm-mentions" data-adm-mentions aria-labelledby="adm-mentions-title">
        <div class="container-site">
            <h2 id="adm-mentions-title" class="adm-h2" data-reveal>{{ $data['mentions_title'] }}</h2>
            <p class="adm-mentions__hint" data-reveal>{{ $data['mentions_hint'] }}</p>

            <div class="adm-cards" data-adm-cards>
                @foreach ($data['mentions'] as $i => $mention)
                    @php($formation = $mention['formation'] ? $formations->get($mention['formation']) : null)
                    <article class="adm-card {{ $i === 0 ? 'is-open' : '' }}" data-adm-card>
                        <h3 class="adm-card__head">
                            <button type="button" class="adm-card__btn" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="adm-card-{{ $i }}" data-adm-toggle>
                                <span class="adm-card__num" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="adm-card__name">{{ $mention['name'] }}</span>
                                <span class="adm-card__plus" aria-hidden="true"></span>
                            </button>
                        </h3>
                        <div id="adm-card-{{ $i }}" class="adm-card__body" data-adm-body>
                            @if ($mention['tracks'])
                                <p class="adm-card__label">{{ __('Parcours') }}</p>
                                <ul class="adm-tracks" role="list">@foreach ($mention['tracks'] as $track)<li>{{ $track }}</li>@endforeach</ul>
                            @endif
                            @isset($mention['test'])
                                <p class="adm-card__label">{{ __('Test d\'admission') }}</p>
                                <p>{{ $mention['test'] }}</p>
                                <p class="adm-card__label">{{ __('Séries de Baccalauréat') }}</p>
                                <p>{{ $mention['series'] }}</p>
                            @endisset
                            @isset($mention['requires'])
                                <p class="adm-card__label">{{ __('Licence exigée') }}</p>
                                <p>{{ $mention['requires'] }}</p>
                            @endisset
                            @if ($formation)
                                <a href="{{ route('formations.show', $formation->slug) }}" class="link-arrow mt-5 w-fit">{{ __('Voir la formation') }}</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. LE DÉPÔT : coordonnées et affiche officielle --}}
    <section class="adm-contact on-dark" data-adm-contact aria-labelledby="adm-contact-title">
        <div class="container-site grid items-center gap-14 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20">
            <div>
                <h2 id="adm-contact-title" class="adm-contact__title" data-reveal>{{ __('Déposer son dossier') }}</h2>
                <address class="adm-address not-italic" data-reveal>
                    <p class="flex gap-3"><x-ico name="map-pin" class="mt-1 size-5 shrink-0 text-gold-300" />{{ $contact['address'] }}</p>
                    <p class="flex gap-3"><x-ico name="mail" class="mt-1 size-5 shrink-0 text-gold-300" /><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>
                    <p class="flex gap-3"><x-ico name="phone" class="mt-1 size-5 shrink-0 text-gold-300" /><span>@foreach ($contact['phones'] as $phone)<a href="tel:{{ preg_replace('/[^+\d]/', '', '+261'.ltrim(str_replace(' ', '', $phone), '0')) }}">{{ $phone }}</a>@if (! $loop->last) · @endif @endforeach</span></p>
                </address>
                <div class="mt-10" data-reveal>
                    <x-button :href="$registration" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square">{{ __('S\'inscrire') }}<span class="sr-only"> {{ __('(nouvel onglet)') }}</span></x-button>
                </div>
            </div>

            @if ($poster)
                <a href="{{ $poster->absoluteUrl() }}" target="_blank" rel="noopener noreferrer" class="adm-poster" data-adm-tilt data-reveal data-reveal-delay="0.15">
                    <span class="adm-poster__sheet" data-adm-tilt-sheet>
                        <x-picture :asset="$poster" conversion="card" sizes="(min-width: 1024px) 26rem, 80vw" :alt="'Affiche officielle d\'admission '.$data['title'].' '.$common['year']" />
                    </span>
                    <span class="adm-poster__cta">{{ __('Affiche officielle en grand') }} <x-ico name="arrow-up-right" class="size-4" /><span class="sr-only"> {{ __('(nouvel onglet)') }}</span></span>
                </a>
            @endif
        </div>
    </section>
</div>
</x-layouts.app>
