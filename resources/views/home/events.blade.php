@php
    // Le prochain rendez-vous porte un compte à rebours ; la phrase « Dans N jours » est calculée des dates (comme sur la page Événements).
    $now = now();
    $next = $events->first();
    $left = $next && $next->starts_at->gt($now) ? (int) $now->diffInSeconds($next->starts_at, false) : null;
    $when = function ($event) use ($now) {
        $days = (int) $now->copy()->startOfDay()->diffInDays($event->starts_at->copy()->startOfDay(), false);
        $ongoing = $event->starts_at->lte($now) && ($days < 0 || ($event->ends_at && $event->ends_at->gte($now) && ! $event->is_all_day));

        return match (true) {
            $ongoing => __('events.when.now'),
            $days <= 0 => __('events.when.today'),
            $days === 1 => __('events.when.tomorrow'),
            default => trans_choice('events.when.in', $days),
        };
    };
@endphp

{{-- Événements : des billets, le premier avec son compte à rebours (animations : home.js et events.js pour le compte à rebours). --}}
<section class="home-evt on-dark" data-home="evt" data-events="home" aria-labelledby="home-events-title">
    <div class="container-site">
        <header class="home-evt__head">
            <div>
                <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ __('home.events.eyebrow') }}</p>
                <h2 id="home-events-title" class="home-evt__title" data-reveal>{{ __('home.events.title') }}</h2>
            </div>
            <a href="{{ url('/evenements') }}" class="link-arrow text-white!" data-reveal>{{ __('home.events.link') }} <x-ico name="arrow-right" class="size-4" /></a>
        </header>

        <ul class="home-evt__row">
            @foreach ($events as $event)
                <li class="home-ticket" data-home-ticket>
                    <div class="home-ticket__stub">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}">
                            <span class="home-ticket__week">{{ $event->starts_at->translatedFormat('l') }}</span>
                            <span class="home-ticket__day">{{ $event->starts_at->format('j') }}</span>
                            <span class="home-ticket__month">{{ $event->starts_at->translatedFormat('F Y') }}</span>
                        </time>
                    </div>
                    <div class="home-ticket__main">
                        <p class="home-ticket__when">{{ $when($event) }}</p>
                        <h3 class="home-ticket__name"><a href="{{ $event->url() }}" class="stretched-link">{{ $event->title }}</a></h3>
                        <p class="home-ticket__meta">
                            <span><x-ico name="clock" class="size-4" />@if ($event->is_all_day){{ __('Toute la journée') }}@else{{ \App\Support\Locales::time($event->starts_at) }}@if ($event->ends_at) – {{ \App\Support\Locales::time($event->ends_at) }}@endif @endif</span>
                            @if ($event->location)<span><x-ico name="map-pin" class="size-4" />{{ $event->location }}</span>@endif
                        </p>
                        @if ($loop->first && $left !== null)
                            @php($parts = ['d' => intdiv($left, 86400), 'h' => intdiv($left % 86400, 3600), 'm' => intdiv($left % 3600, 60), 's' => $left % 60])
                            <div class="evt-count home-ticket__count" data-evt-countdown="{{ $event->starts_at->toIso8601String() }}" aria-hidden="true">
                                @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $unit => $label)
                                    <div class="evt-count__cell"><b data-unit="{{ $unit }}">{{ str_pad((string) $parts[$unit], 2, '0', STR_PAD_LEFT) }}</b><i>{{ __('events.count.'.$label) }}</i></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
