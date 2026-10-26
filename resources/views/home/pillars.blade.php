{{-- Spiritus · Manus · Mens : le triangle se dessine au défilement, les trois mots s'allument tour à tour (animations/pillars.js). --}}
<section data-pillars class="on-dark relative isolate overflow-hidden bg-navy-900 py-24 text-white md:py-32" aria-labelledby="pillars-title">
    <x-madagascar class="pointer-events-none absolute top-1/2 left-1/2 -z-10 h-[115%] -translate-x-1/2 -translate-y-1/2 text-navy-800/45" />

    <div class="container-site">
        <header class="mx-auto max-w-3xl text-center">
            <p class="eyebrow mb-4" data-reveal>{{ __('home.pillars.eyebrow') }}</p>
            <h2 id="pillars-title" class="text-h2 text-white!" data-reveal>{{ __('home.pillars.title') }}</h2>
            <p class="mt-5 text-lead text-navy-100" data-reveal data-reveal-delay="0.1">{{ __('home.pillars.intro') }}</p>
        </header>

        <div class="relative mx-auto mt-12 w-full max-w-[44rem] md:mt-16">
            <div class="relative aspect-[100/90]" aria-hidden="true">
                <svg viewBox="0 0 100 90" class="absolute inset-0 size-full overflow-visible" fill="none">
                    <path d="M3 4h94L50 86z" pathLength="1" stroke="var(--color-gold-300)" stroke-width="0.6" stroke-dasharray="1" stroke-dashoffset="0" data-pillars-triangle />
                </svg>
                <x-madagascar class="absolute top-[18%] left-[37%] h-[56%] text-navy-700" data-pillars-island />
                <span class="absolute top-[3%] left-1/2 -translate-x-1/2 -translate-y-[170%] font-serif text-lg tracking-[0.35em] text-gold-300 uppercase sm:text-2xl" data-pillars-word>{{ __('home.pillars.spiritus.word') }}</span>
                <span class="absolute top-[34%] left-[8%] -rotate-[58deg] font-serif text-lg tracking-[0.35em] text-gold-300 uppercase sm:text-2xl" data-pillars-word>{{ __('home.pillars.manus.word') }}</span>
                <span class="absolute top-[34%] right-[8%] rotate-[58deg] font-serif text-lg tracking-[0.35em] text-gold-300 uppercase sm:text-2xl" data-pillars-word>{{ __('home.pillars.mens.word') }}</span>
                <span class="absolute top-[58%] left-1/2 -translate-x-1/2 font-serif text-3xl text-white sm:text-5xl" data-pillars-word>{{ __('home.pillars.service') }}</span>
            </div>
        </div>

        <dl class="mx-auto mt-14 grid max-w-5xl gap-10 text-center md:grid-cols-3 md:gap-8">
            @foreach (['spiritus', 'manus', 'mens'] as $pillar)
                <div data-reveal data-reveal-delay="{{ $loop->index * 0.1 }}">
                    <dt class="font-serif text-3xl text-white">{{ __("home.pillars.$pillar.word") }} <span class="mt-1 block font-sans text-xs font-semibold tracking-[0.2em] text-gold-300 uppercase">{{ __("home.pillars.$pillar.label") }}</span></dt>
                    <dd class="mt-4 text-navy-100">{{ __("home.pillars.$pillar.text") }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
