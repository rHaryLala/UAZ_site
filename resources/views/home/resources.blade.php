@php
    // Pages « ressources » (maquette : Bibliothèque, Emplois du temps, Téléchargements) : celles qui existent et sont publiées.
    $resources = \App\Models\Page::visible()->whereIn('key', ['student-life-library', 'timetables', 'admissions-downloads'])->get()
        ->sortBy(fn ($p) => array_search($p->key, ['student-life-library', 'timetables', 'admissions-downloads']))->values();
@endphp

@if ($resources->isNotEmpty())
    <x-section tone="sand" :eyebrow="__('home.resources.eyebrow')" :title="__('home.resources.title')">
        <ol class="divide-y divide-ink-200/60 border-y border-ink-200/60">
            @foreach ($resources as $resource)
                <li data-reveal>
                    <a href="{{ $resource->url() }}" class="group flex items-center gap-6 py-6 md:py-8">
                        <span class="font-serif text-3xl text-gold-600 md:text-5xl" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="flex-1">
                            <span class="eyebrow block">{{ __('Ressource') }}</span>
                            <span class="mt-1 block font-serif text-h3 text-navy-800 transition-transform duration-300 group-hover:translate-x-2">{{ $resource->title }}</span>
                            @if ($resource->summary)<span class="mt-1 block text-sm text-ink-700">{{ $resource->summary }}</span>@endif
                        </span>
                        <x-ico name="arrow-up-right" class="size-6 text-navy-800 transition-transform duration-300 group-hover:-translate-y-1 group-hover:translate-x-1" />
                    </a>
                </li>
            @endforeach
        </ol>
    </x-section>
@endif
