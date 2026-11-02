@props(['event'])

<article {{ $attributes->class('card group flex gap-5 p-5 md:p-6') }}>
    <time datetime="{{ $event->starts_at->toDateString() }}" class="flex w-16 shrink-0 flex-col items-center rounded-xl bg-navy-800 py-3 text-white">
        <span class="font-serif text-3xl leading-none">{{ $event->starts_at->format('j') }}</span>
        <span class="mt-1 text-xs font-semibold tracking-[0.14em] text-gold-300 uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
    </time>
    <div class="min-w-0">
        <h3 class="text-h4 font-serif"><a href="{{ $event->url() }}" class="stretched-link hover:text-navy-600">{{ $event->title }}</a></h3>
        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-700">
            @unless ($event->is_all_day)
                <span class="inline-flex items-center gap-1.5"><x-ico name="clock" class="size-4" />{{ \App\Support\Locales::time($event->starts_at) }}@if ($event->ends_at) – {{ \App\Support\Locales::time($event->ends_at) }}@endif</span>
            @else
                <span class="inline-flex items-center gap-1.5"><x-ico name="clock" class="size-4" />{{ __('Toute la journée') }}</span>
            @endunless
            @if ($event->location)
                <span class="inline-flex items-center gap-1.5"><x-ico name="map-pin" class="size-4" />{{ $event->location }}</span>
            @endif
        </p>
    </div>
</article>
