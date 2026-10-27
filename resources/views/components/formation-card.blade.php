@props(['formation', 'eager' => false, 'level' => 'h3'])

{{-- Carte formation : image plein cadre, voile marine, résumé qui apparaît au survol / focus (toujours visible sur mobile). --}}
<article {{ $attributes->class('card on-dark group relative isolate flex min-h-[22rem] flex-col justify-end overflow-hidden bg-navy-900 text-white') }}>
    @if ($formation->cover)
        <x-picture :asset="$formation->cover" conversion="card" sizes="(min-width: 1024px) 40vw, 92vw" :eager="$eager" decorative
            class="absolute inset-0 -z-10 size-full object-cover opacity-80 transition-transform duration-[1200ms] ease-out group-hover:scale-105" />
    @else
        <x-madagascar class="absolute -right-10 -bottom-10 -z-10 h-[120%] text-navy-700/60 transition-transform duration-[1200ms] ease-out group-hover:scale-105" />
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950 via-navy-900/70 to-navy-900/10" aria-hidden="true"></div>

    <div class="p-6 md:p-8">
        <p class="eyebrow mb-3">
            {{ $formation->level->getLabel() }}@if ($formation->faculty) · {{ $formation->faculty->name }}@endif
        </p>
        <{{ $level }} class="text-h3 text-white!">
            <a href="{{ $formation->url() }}" class="stretched-link">{{ $formation->title }}</a>
        </{{ $level }}>

        @if ($formation->summary)
            <p class="mt-3 max-h-0 overflow-hidden text-sm text-navy-100 opacity-0 transition-all duration-500 ease-out group-hover:max-h-32 group-hover:opacity-100 group-focus-within:max-h-32 group-focus-within:opacity-100 max-md:max-h-none max-md:opacity-100">
                {{ $formation->summary }}
            </p>
        @endif

        <p class="mt-5 flex items-center gap-4 text-xs font-semibold tracking-wide text-gold-300">
            @if ($formation->duration)<span>{{ $formation->duration }}</span>@endif
            @if ($formation->credits)<span>{{ $formation->credits }} ECTS</span>@endif
            <x-ico name="arrow-up-right" class="ml-auto size-5 transition-transform duration-300 group-hover:translate-x-1 group-hover:-translate-y-1" />
        </p>
    </div>
</article>
