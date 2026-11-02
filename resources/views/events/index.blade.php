@php
    use App\Support\EventDial;

    $now = now();
    $C = EventDial::CENTER;

    // « Dans 12 jours », « Demain », « En cours » : toujours tiré des dates de l'événement.
    $when = function ($event) use ($now) {
        $started = $event->starts_at->lte($now);
        $days = (int) $now->copy()->startOfDay()->diffInDays($event->starts_at->copy()->startOfDay(), false);

        // En cours : un événement à horaires qui a commencé et n'est pas fini, ou un événement de plusieurs jours déjà entamé.
        $ongoing = $started && ($days < 0 || ($event->ends_at && $event->ends_at->gte($now) && ! $event->is_all_day));

        return match (true) {
            $ongoing => __('events.when.now'),
            $days <= 0 => __('events.when.today'),
            $days === 1 => __('events.when.tomorrow'),
            default => trans_choice('events.when.in', $days),
        };
    };

    // Le cadran de l'année : aujourd'hui, les événements des douze prochains mois, l'arc qui mène au prochain.
    $todayAngle = EventDial::angle($now);
    $dots = $upcoming->filter(fn ($event) => $event->starts_at->lt($now->copy()->addYear()) && $event->id !== $next?->id)
        ->map(fn ($event) => EventDial::angle($event->starts_at))->values();
    $arc = $next ? EventDial::arc($todayAngle, EventDial::angle($next->starts_at), 128) : '';
    $monthStarts = collect(range(1, 12))->map(fn ($m) => EventDial::angle(\Illuminate\Support\Carbon::create($now->year, $m, 1)));
    $months = collect(range(1, 12))->map(function ($m) use ($now, $monthStarts) {
        $from = $monthStarts[$m - 1];
        $to = $m === 12 ? 360 : $monthStarts[$m];

        return ['label' => \Illuminate\Support\Carbon::create($now->year, $m, 1)->translatedFormat('M'), 'start' => $from, 'mid' => ($from + $to) / 2];
    });

    // Compte à rebours initial (le script le fait ensuite avancer chaque seconde).
    $remaining = $next && $next->starts_at->gt($now) ? max(0, $now->diffInSeconds($next->starts_at, false)) : null;
    $parts = $remaining === null ? null : [
        'd' => intdiv((int) $remaining, 86400), 'h' => intdiv((int) $remaining % 86400, 3600),
        'm' => intdiv((int) $remaining % 3600, 60), 's' => (int) $remaining % 60,
    ];
@endphp

<x-layouts.app :title="__('Événements')" :description="__('Les événements à venir et passés de l\'Université Adventiste Zurcher.')">
    {{-- 1. LE CADRAN : l'année tourne, aujourd'hui avance, les rendez-vous se posent (animations : resources/js/animations/events.js) --}}
    <header class="evt-hero on-dark" data-events data-evt-hero aria-labelledby="evt-title">
        <div class="container-site">
            <x-breadcrumbs :items="[['label' => __('Événements')]]" dark />
        </div>

        <div class="container-site evt-hero__inner">
            <div class="evt-hero__text">
                <p class="eyebrow mb-5 text-sun-400!" data-evt-hide data-evt-fade>{{ __('events.hero.eyebrow') }}</p>
                <h1 id="evt-title" class="evt-title" data-evt-title>{{ __('Événements') }}</h1>
                <p class="evt-lead" data-evt-hide data-evt-fade>{{ __('Rencontres, cultes, concours, journées portes ouvertes : retrouvez la vie du campus.') }}</p>

                @if ($next)
                    <section class="evt-next" data-evt-hide data-evt-fade aria-labelledby="evt-next-title">
                        <p class="evt-next__eyebrow" id="evt-next-title">{{ __('events.next.eyebrow') }}</p>
                        <h2 class="evt-next__title"><a href="{{ $next->url() }}">{{ $next->title }}</a></h2>
                        <p class="evt-next__meta">
                            <time datetime="{{ $next->starts_at->toIso8601String() }}">{{ $next->starts_at->translatedFormat('l j F Y') }}</time>
                            @if ($next->location)<span aria-hidden="true">·</span><span>{{ $next->location }}</span>@endif
                        </p>

                        @if ($parts)
                            <p class="sr-only">{{ $when($next) }}</p>
                            <div class="evt-count" data-evt-countdown="{{ $next->starts_at->toIso8601String() }}" aria-hidden="true">
                                @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $unit => $label)
                                    <div class="evt-count__cell"><b data-unit="{{ $unit }}">{{ str_pad((string) $parts[$unit], 2, '0', STR_PAD_LEFT) }}</b><i>{{ __('events.count.'.$label) }}</i></div>
                                @endforeach
                            </div>
                        @else
                            <p class="evt-live"><span aria-hidden="true"></span>{{ $when($next) }}</p>
                        @endif
                    </section>
                @else
                    <p class="evt-lead mt-8" data-evt-hide data-evt-fade>{{ __('events.upcoming.empty') }}</p>
                @endif
            </div>

            {{-- Le cadran : décor (les événements sont listés dessous) --}}
            <div class="evt-dial" aria-hidden="true" data-evt-dial>
                <svg viewBox="0 0 400 400" focusable="false">
                    <circle class="evt-dial__ring" cx="{{ $C }}" cy="{{ $C }}" r="185" data-evt-ring />
                    <circle class="evt-dial__inner" cx="{{ $C }}" cy="{{ $C }}" r="128" />
                    @foreach ($months as $m)
                        @php([$x1, $y1] = EventDial::point($m['start'], 185))
                        @php([$x2, $y2] = EventDial::point($m['start'], 166))
                        @php([$tx, $ty] = EventDial::point($m['mid'], 150))
                        <line class="evt-dial__tick" x1="{{ $x1 }}" y1="{{ $y1 }}" x2="{{ $x2 }}" y2="{{ $y2 }}" data-evt-tick />
                        <text class="evt-dial__month" x="{{ $tx }}" y="{{ $ty }}" text-anchor="middle" dominant-baseline="central" data-evt-month>{{ $m['label'] }}</text>
                    @endforeach

                    <text class="evt-dial__year" x="{{ $C }}" y="{{ $C }}" text-anchor="middle" dominant-baseline="central">{{ $now->year }}</text>

                    @if ($arc)<path class="evt-dial__arc" d="{{ $arc }}" data-evt-arc />@endif

                    @foreach ($dots as $angle)
                        <g class="evt-dial__turn" style="transform: rotate({{ $angle }}deg)" data-evt-turn="{{ $angle }}"><circle class="evt-dial__dot" cx="{{ $C }}" cy="15" r="5" /></g>
                    @endforeach
                    @if ($next)
                        <g class="evt-dial__turn" style="transform: rotate({{ EventDial::angle($next->starts_at) }}deg)" data-evt-turn="{{ EventDial::angle($next->starts_at) }}"><circle class="evt-dial__next" cx="{{ $C }}" cy="15" r="9" data-evt-next-dot /></g>
                    @endif
                    <g class="evt-dial__turn" style="transform: rotate({{ $todayAngle }}deg)" data-evt-turn="{{ $todayAngle }}" data-evt-today><path class="evt-dial__today" d="M{{ $C }} 33 l-9 -20 h18 z" /></g>
                </svg>
            </div>
        </div>
    </header>

    {{-- 2. LE PROGRAMME : un fil se tend d'un rendez-vous à l'autre --}}
    <section class="evt-upcoming" data-evt-upcoming aria-labelledby="evt-upcoming-title">
        <div class="container-site">
            <p class="eyebrow mb-3" data-reveal>{{ __('events.upcoming.eyebrow') }}</p>
            <h2 id="evt-upcoming-title" class="evt-h2" data-reveal>{{ __('À venir') }}</h2>

            @if ($upcoming->isEmpty())
                <p class="evt-empty" data-reveal>{{ __('events.upcoming.empty') }}</p>
            @else
                <ol class="evt-line" data-evt-line>
                    <span class="evt-line__rail" aria-hidden="true"><i data-evt-fill></i></span>
                    @foreach ($upcoming as $event)
                        <li class="evt-item" data-evt-item>
                            <time class="evt-date" datetime="{{ $event->starts_at->toDateString() }}">
                                <span class="evt-date__week">{{ $event->starts_at->translatedFormat('l') }}</span>
                                <span class="evt-date__day" data-evt-day>{{ $event->starts_at->format('j') }}</span>
                                <span class="evt-date__month">{{ $event->starts_at->translatedFormat('F Y') }}</span>
                                @if ($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at))
                                    <span class="evt-date__until">{{ __('au') }} {{ $event->ends_at->translatedFormat('j F') }}</span>
                                @endif
                            </time>
                            <span class="evt-node" aria-hidden="true" data-evt-node></span>

                            <article class="evt-card" data-evt-card>
                                <div class="evt-card__media" data-evt-media>
                                    @if ($event->cover)
                                        <x-picture :asset="$event->cover" conversion="card" sizes="(min-width: 1024px) 26rem, (min-width: 640px) 40vw, 92vw" decorative class="evt-card__img" data-evt-img />
                                    @else
                                        <span class="evt-card__blank" aria-hidden="true">{{ $event->starts_at->format('j') }}</span>
                                    @endif
                                    <span class="evt-card__when">{{ $when($event) }}</span>
                                </div>
                                <div class="evt-card__body">
                                    <h3 class="evt-card__title"><a href="{{ $event->url() }}" class="stretched-link" data-evt-link>{{ $event->title }}</a></h3>
                                    @if ($event->summary)<p class="evt-card__summary">{{ $event->summary }}</p>@endif
                                    <p class="evt-card__meta">
                                        <span><x-ico name="clock" class="size-4" />@if ($event->is_all_day){{ __('Toute la journée') }}@else{{ \App\Support\Locales::time($event->starts_at) }}@if ($event->ends_at) – {{ \App\Support\Locales::time($event->ends_at) }}@endif @endif</span>
                                        @if ($event->location)<span><x-ico name="map-pin" class="size-4" />{{ $event->location }}</span>@endif
                                    </p>
                                    @if ($event->registration_url)
                                        <a href="{{ $event->registration_url }}" target="_blank" rel="noopener noreferrer" class="evt-card__register">{{ __('S\'inscrire') }}<span class="sr-only"> — {{ $event->title }} ({{ __('timetables.new_tab') }})</span> <x-ico name="arrow-up-right" class="size-4" /></a>
                                    @endif
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
        <span class="evt-cursor" aria-hidden="true" data-evt-cursor>{{ __('events.cursor') }}</span>
    </section>

    {{-- 3. LES ARCHIVES : les événements passés, en noir et blanc, retrouvent leurs couleurs au survol --}}
    @if ($past->isNotEmpty())
        <section class="evt-past on-dark" data-evt-past aria-labelledby="evt-past-title">
            <div class="container-site">
                <p class="eyebrow mb-3 text-sun-400!">{{ __('events.past.eyebrow') }}</p>
                <h2 id="evt-past-title" class="evt-h2 text-white!">{{ __('Événements passés') }}</h2>

                <ul class="evt-archive">
                    @foreach ($past as $event)
                        <li class="evt-stamp" data-evt-stamp>
                            <article>
                                <div class="evt-stamp__media">
                                    @if ($event->cover)
                                        <x-picture :asset="$event->cover" conversion="card" sizes="(min-width: 1024px) 24rem, (min-width: 640px) 45vw, 92vw" decorative class="evt-stamp__img" />
                                    @else
                                        <span class="evt-stamp__blank" aria-hidden="true">{{ $event->starts_at->format('j') }}</span>
                                    @endif
                                    <time class="evt-stamp__date" datetime="{{ $event->starts_at->toDateString() }}"><b>{{ $event->starts_at->format('j') }}</b>{{ $event->starts_at->translatedFormat('M Y') }}</time>
                                </div>
                                <h3 class="evt-stamp__title"><a href="{{ $event->url() }}" class="stretched-link">{{ $event->title }}</a></h3>
                                @if ($event->location)<p class="evt-stamp__meta">{{ $event->location }}</p>@endif
                            </article>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-14">{{ $past->links() }}</div>
            </div>
        </section>
    @endif

    {{-- 4. LA SUITE --}}
    <section class="evt-more on-dark" data-evt-more aria-labelledby="evt-more-title">
        <div class="container-site">
            <h2 id="evt-more-title" class="evt-more__text" data-evt-more-text>{{ __('events.more.text') }}</h2>
            <p class="mt-10 flex flex-wrap gap-4" data-reveal>
                <button type="button" data-newsletter-open aria-haspopup="dialog" class="btn btn-sun btn-square">{{ __('events.more.subscribe') }}</button>
                <x-button :href="url('/actualites')" variant="outline">{{ __('events.more.news') }}</x-button>
            </p>
        </div>
    </section>
</x-layouts.app>
