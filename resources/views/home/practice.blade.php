{{-- « Apprentissage pratique » : grand bandeau typographique avec parallaxe douce sur Madagascar. --}}
<section class="relative isolate overflow-hidden bg-paper-200 py-24 md:py-36" aria-labelledby="practice-title">
    <x-madagascar class="pointer-events-none absolute top-1/2 right-[-6rem] -z-10 h-[150%] -translate-y-1/2 text-paper-300 lg:right-[6%]" data-parallax="-12" />

    <div class="container-site">
        <p class="eyebrow mb-5" data-reveal>{{ __('home.practice.eyebrow') }}</p>
        <h2 id="practice-title" class="max-w-4xl text-h1" data-reveal>{{ __('home.practice.title') }}</h2>
        <p class="mt-8 max-w-2xl text-lead text-ink-700" data-reveal data-reveal-delay="0.1">{{ __('home.practice.text') }}</p>
        <div class="mt-10" data-reveal data-reveal-delay="0.2">
            <x-button :href="url('/formations')" variant="navy" arrow>{{ __('home.formations.link') }}</x-button>
        </div>
    </div>
</section>
