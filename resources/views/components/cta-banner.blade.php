@props([
    'title',
    'text' => null,
    'primary' => null, // ['label' => ..., 'url' => ...]
    'secondary' => null,
    'eyebrow' => null,
])

{{-- Bandeau d'appel à l'action : fond marine, triangle du sceau en filigrane. --}}
<section {{ $attributes->class('on-dark relative isolate overflow-hidden bg-navy-900 text-white') }}>
    <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(90rem,160vw)] -translate-x-1/2 -translate-y-[44%] text-gold-300/15" />
    <x-madagascar class="pointer-events-none absolute -right-8 bottom-0 -z-10 hidden h-[130%] translate-y-[28%] text-navy-800 lg:block" />

    <div class="container-site section-y text-center">
        @if ($eyebrow)
            <p class="eyebrow mb-5" data-reveal>{{ $eyebrow }}</p>
        @endif
        <h2 class="mx-auto max-w-4xl text-h1 text-white!" data-reveal>{{ $title }}</h2>
        @if ($text)
            <p class="mx-auto mt-6 max-w-2xl text-lead text-navy-100" data-reveal data-reveal-delay="0.1">{{ $text }}</p>
        @endif
        <div class="mt-10 flex flex-wrap justify-center gap-4" data-reveal data-reveal-delay="0.2">
            @if ($primary)
                <x-button :href="$primary['url']" variant="gold" arrow>{{ $primary['label'] }}</x-button>
            @endif
            @if ($secondary)
                <x-button :href="$secondary['url']" variant="outline">{{ $secondary['label'] }}</x-button>
            @endif
            {{ $slot }}
        </div>
    </div>
</section>
