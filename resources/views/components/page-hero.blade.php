@props([
    'title',
    'eyebrow' => null,
    'intro' => null,
    'breadcrumbs' => [],
    'image' => null, // MediaAsset
])

{{-- En-tête des pages intérieures : marine, silhouette de Madagascar en filigrane, image facultative. --}}
<header {{ $attributes->class('on-dark relative isolate overflow-hidden bg-navy-900 text-white') }}>
    @if ($image)
        <x-picture :asset="$image" conversion="hero" sizes="100vw" decorative eager class="absolute inset-0 -z-20 size-full object-cover opacity-35" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-navy-950/90 via-navy-900/70 to-navy-900/30" aria-hidden="true"></div>
    @else
        <x-madagascar class="pointer-events-none absolute top-1/2 -right-6 -z-10 h-[150%] -translate-y-1/2 text-navy-800/80 sm:right-[6%]" />
        <x-triangle class="pointer-events-none absolute -bottom-1/3 left-[-8%] -z-10 w-[48rem] text-gold-300/10" />
    @endif

    <div class="container-site pt-[calc(var(--header-h)+2.5rem)] pb-14 md:pb-20">
        @if (count($breadcrumbs))
            <x-breadcrumbs :items="$breadcrumbs" dark class="mb-8" />
        @endif
        @if ($eyebrow)
            <p class="eyebrow mb-4">{{ $eyebrow }}</p>
        @endif
        <h1 class="max-w-4xl text-h1 text-white!">{{ $title }}</h1>
        @if ($intro)
            <p class="mt-6 max-w-2xl text-lead text-navy-100">{{ $intro }}</p>
        @endif
        {{ $slot }}
    </div>
</header>
