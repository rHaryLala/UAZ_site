@php
    use App\Support\EventCalendar;
    use App\Support\Locales;
    use Illuminate\Support\Carbon;

    $now = now();
    $start = $event->starts_at;
    $end = $event->ends_at;
    $allDay = $event->is_all_day;
    $cover = $event->cover && $event->cover->file() ? $event->cover : null;

    // Où en est l'événement : terminé, en cours, ou à venir (aujourd'hui, demain, dans N jours).
    $past = $end ? $end->lt($now) : $start->lt($now->copy()->startOfDay());
    $ongoing = ! $past && $start->lte($now) && $end && ! $allDay;
    $daysAway = (int) $now->copy()->startOfDay()->diffInDays($start->copy()->startOfDay(), false);
    $status = match (true) {
        $past => __('events.show.past'),
        $ongoing => __('events.when.now'),
        $daysAway <= 0 => __('events.when.today'),
        $daysAway === 1 => __('events.when.tomorrow'),
        default => trans_choice('events.when.in', $daysAway),
    };
    $remaining = ! $past && ! $ongoing && $start->gt($now) ? (int) $now->diffInSeconds($start, false) : null;
    $parts = $remaining === null ? null : [
        'd' => intdiv($remaining, 86400), 'h' => intdiv($remaining % 86400, 3600), 'm' => intdiv($remaining % 3600, 60), 's' => $remaining % 60,
    ];

    // Le déroulé : une journée de 24 h (horaire connu), ou les jours de l'événement (plusieurs jours).
    $span = $end ? (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1 : 1;
    $mode = match (true) {
        $span > 14 => 'long',
        $span > 1 => 'days',
        $allDay => 'allday',
        (bool) $end => 'track',
        default => 'point',
    };
    $minutes = fn (Carbon $date) => $date->hour * 60 + $date->minute;
    $left = round($minutes($start) / 1440 * 100, 2);
    $width = $end ? max(1.5, round((min(1440, $minutes($end)) - $minutes($start)) / 1440 * 100, 2)) : 0;
    $hour = fn (int $h) => $h === 24 ? (Locales::isDefault() ? '24h00' : '24:00') : Locales::time(Carbon::createFromTime($h, 0));
    $period = collect(range(0, min($span, 14) - 1))->map(fn ($i) => $start->copy()->startOfDay()->addDays($i));
@endphp

<x-layouts.app :title="$event->metaTitle()" :description="$event->metaDescription()" :image="$event->metaImage()" :noindex="! $event->isLive()">
    {{-- 1. L'AFFICHE : la photo respire, le titre se lève, le billet se pose (animations : resources/js/animations/events.js) --}}
    <header class="evs-hero on-dark" data-events="show" data-evs-hero aria-labelledby="evs-title">
        @if ($cover)
            <div class="evs-hero__cover" data-evs-cover aria-hidden="true">
                <x-picture :asset="$cover" conversion="hero" sizes="100vw" decorative eager class="size-full object-cover" />
            </div>
        @endif
        <div class="evs-hero__shade" aria-hidden="true"></div>

        <div class="container-site">
            <x-breadcrumbs :items="[['label' => __('Événements'), 'url' => url('/evenements')], ['label' => str($event->title)->limit(48)->toString()]]" dark />
        </div>

        <div class="container-site evs-hero__inner">
            <div class="evs-hero__text">
                <p class="evs-tag" data-evs-hide data-evs-fade>@if ($past){{ $status }}@else{{ __('Événement') }} · {{ $status }}@endif</p>
                <h1 id="evs-title" class="evs-title" data-evs-title>{{ $event->title }}</h1>
                @if ($event->summary)<p class="evs-lead" data-evs-hide data-evs-fade>{{ $event->summary }}</p>@endif

                @if ($parts)
                    <div class="evt-count mt-8" data-evs-hide data-evs-fade data-evt-countdown="{{ $start->toIso8601String() }}" aria-hidden="true">
                        @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $unit => $label)
                            <div class="evt-count__cell"><b data-unit="{{ $unit }}">{{ str_pad((string) $parts[$unit], 2, '0', STR_PAD_LEFT) }}</b><i>{{ __('events.count.'.$label) }}</i></div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Le billet : la date, l'horaire, le lieu, l'inscription et l'agenda --}}
            <aside class="evs-ticket" data-evs-ticket aria-label="{{ __('Informations pratiques') }}">
                <div class="evs-ticket__stub">
                    <dl>
                        <div>
                            <dt class="sr-only">{{ __('Date') }}</dt>
                            <dd>
                                <time datetime="{{ $start->toIso8601String() }}">
                                    <span class="evs-ticket__week">{{ $start->translatedFormat('l') }}</span>
                                    <span class="evs-ticket__day">{{ $start->format('j') }}</span>
                                    <span class="evs-ticket__month">{{ $start->translatedFormat('F Y') }}</span>
                                </time>
                                @if ($end && ! $end->isSameDay($start))
                                    <span class="evs-ticket__until">{{ __('au') }} <time datetime="{{ $end->toIso8601String() }}">{{ $end->translatedFormat('l j F Y') }}</time></span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="evs-ticket__tear" aria-hidden="true"></div>

                <div class="evs-ticket__main">
                    <dl class="evs-ticket__info">
                        <div>
                            <dt class="sr-only">{{ __('Horaire') }}</dt>
                            <dd><x-ico name="clock" class="size-5" />@if ($allDay){{ __('Toute la journée') }}@else{{ Locales::time($start) }}@if ($end) – {{ Locales::time($end) }}@endif @endif</dd>
                        </div>
                        @if ($event->location)
                            <div>
                                <dt class="sr-only">{{ __('Lieu') }}</dt>
                                <dd><x-ico name="map-pin" class="size-5" /><span>{{ $event->location }}
                                    @if ($event->location_url)<a href="{{ $event->location_url }}" target="_blank" rel="noopener noreferrer">{{ __('Voir le plan (nouvel onglet)') }}</a>@endif</span></dd>
                            </div>
                        @endif
                    </dl>

                    @unless ($past)
                        <div class="evs-ticket__actions">
                            @if ($event->registration_url)
                                <x-button :href="$event->registration_url" variant="sun" arrow class="btn-square" target="_blank" rel="noopener noreferrer">{{ __('S\'inscrire') }}<span class="sr-only"> ({{ __('timetables.new_tab') }})</span></x-button>
                            @endif
                            <p class="evs-ticket__cal">
                                <span>{{ __('events.show.calendar.title') }}</span>
                                <a href="{{ $event->url().'/agenda.ics' }}" download>{{ __('events.show.calendar.ics') }}</a>
                                <a href="{{ EventCalendar::googleUrl($event) }}" target="_blank" rel="noopener noreferrer">{{ __('events.show.calendar.google') }}<span class="sr-only"> ({{ __('timetables.new_tab') }})</span></a>
                            </p>
                        </div>
                    @endunless
                </div>
            </aside>
        </div>
    </header>

    {{-- 2. LE DÉROULÉ : l'événement dans la journée, ou ses jours --}}
    <section class="evs-when" data-evs-when aria-labelledby="evs-when-title">
        <div class="container-site">
            <p class="eyebrow mb-3" data-reveal>{{ __('events.show.when.eyebrow') }}</p>
            <h2 id="evs-when-title" class="evt-h2" data-reveal>{{ __('events.show.when.title') }}</h2>

            @if ($mode === 'days')
                <ol class="evs-days" data-evs-days>
                    @foreach ($period as $day)
                        <li class="evs-day" data-evs-day>
                            <span class="evs-day__week">{{ $day->translatedFormat('D') }}</span>
                            <b class="evs-day__num">{{ $day->format('j') }}</b>
                            <span class="evs-day__month">{{ $day->translatedFormat('M') }}</span>
                        </li>
                    @endforeach
                </ol>
            @elseif ($mode === 'long')
                <p class="evs-long" data-reveal>{{ trans_choice('events.show.when.days', $span, ['count' => $span]) }} · {{ $start->translatedFormat('j F') }} → {{ $end->translatedFormat('j F Y') }}</p>
            @else
                <div class="evs-track" data-evs-track role="img" aria-label="{{ __('events.show.when.track') }} : {{ $allDay ? __('Toute la journée') : Locales::time($start).($end ? ' – '.Locales::time($end) : '') }}">
                    <div class="evs-track__bar" aria-hidden="true">
                        @if ($mode === 'allday')
                            <i class="evs-track__seg" style="left: 0; width: 100%" data-evs-seg></i>
                        @elseif ($mode === 'track')
                            <i class="evs-track__seg" style="left: {{ $left }}%; width: {{ $width }}%" data-evs-seg></i>
                        @else
                            <i class="evs-track__dot" style="left: {{ $left }}%" data-evs-seg></i>
                        @endif
                    </div>
                    <ul class="evs-track__hours" aria-hidden="true">
                        @foreach ([0, 6, 12, 18, 24] as $h)
                            <li style="left: {{ $h / 24 * 100 }}%">{{ $hour($h) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    {{-- 3. LE DESCRIPTIF --}}
    @if (filled(trim(strip_tags((string) $event->description))))
        <section class="evs-about" data-evs-about aria-labelledby="evs-about-title">
            <div class="container-site evs-about__inner">
                <h2 id="evs-about-title" class="evs-about__title" data-reveal>{{ __('events.show.about') }}</h2>
                <div class="nws-prose evs-prose" data-evs-prose><x-rich :html="$event->description" /></div>
            </div>
        </section>
    @endif

    {{-- 4. LES AUTRES RENDEZ-VOUS --}}
    @if ($others->isNotEmpty())
        <section class="evt-past evt-past--live on-dark" data-evs-others aria-labelledby="evs-others-title">
            <div class="container-site">
                <p class="eyebrow mb-3 text-sun-400!">{{ __('events.show.others.eyebrow') }}</p>
                <h2 id="evs-others-title" class="evt-h2 text-white!">{{ __('events.show.others.title') }}</h2>

                <ul class="evt-archive evt-archive--live">
                    @foreach ($others as $other)
                        <li class="evt-stamp" data-evt-stamp>
                            <article>
                                <div class="evt-stamp__media">
                                    @if ($other->cover)
                                        <x-picture :asset="$other->cover" conversion="card" sizes="(min-width: 1024px) 24rem, (min-width: 640px) 45vw, 92vw" decorative class="evt-stamp__img" />
                                    @else
                                        <span class="evt-stamp__blank" aria-hidden="true">{{ $other->starts_at->format('j') }}</span>
                                    @endif
                                    <time class="evt-stamp__date" datetime="{{ $other->starts_at->toDateString() }}"><b>{{ $other->starts_at->format('j') }}</b>{{ $other->starts_at->translatedFormat('M Y') }}</time>
                                </div>
                                <h3 class="evt-stamp__title"><a href="{{ $other->url() }}" class="stretched-link">{{ $other->title }}</a></h3>
                                @if ($other->location)<p class="evt-stamp__meta">{{ $other->location }}</p>@endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <p class="evs-back"><a href="{{ url('/evenements') }}" class="link-arrow"><x-ico name="arrow-left" class="size-4" /> {{ __('Tous les événements') }}</a></p>

    @push('head')
        @include('partials.jsonld-event', ['event' => $event])
    @endpush
</x-layouts.app>
