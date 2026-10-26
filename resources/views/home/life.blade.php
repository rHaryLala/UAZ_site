@php
    $items = $studentLife->filter(fn ($p) => filled($p->summary))->values();
@endphp

<x-section :eyebrow="__('home.life.eyebrow')" :title="__('home.life.title')" :intro="__('home.life.text')">
    <div class="-mt-4 mb-12" data-reveal>
        <a href="{{ url('/vie-etudiante') }}" class="link-arrow">{{ __('home.life.link') }} <x-ico name="arrow-right" class="size-4" /></a>
    </div>

    @if ($items->isNotEmpty())
        <ul class="grid gap-6 md:grid-cols-3">
            @foreach ($items as $item)
                <li data-reveal data-reveal-delay="{{ $loop->index * 0.1 }}">
                    <article class="card group h-full p-7">
                        @if ($item->cover)
                            <div class="-mx-7 -mt-7 mb-6 aspect-[3/2] overflow-hidden">
                                <x-picture :asset="$item->cover" conversion="card" sizes="(min-width: 768px) 30vw, 92vw" class="size-full object-cover transition-transform duration-700 group-hover:scale-105" />
                            </div>
                        @endif
                        <h3 class="text-h4 font-serif"><a href="{{ $item->url() }}" class="stretched-link hover:text-navy-600">{{ $item->title }}</a></h3>
                        <p class="mt-3 text-sm text-ink-700">{{ $item->summary }}</p>
                        <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-navy-800" aria-hidden="true">En savoir plus <x-ico name="arrow-up-right" class="size-4" /></span>
                    </article>
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
