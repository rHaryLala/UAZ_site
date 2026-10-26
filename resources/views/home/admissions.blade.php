{{-- Bandeau marine : message d'admission + visuel graphique (remplaçable par une photo du campus). --}}
<section class="on-dark relative isolate overflow-hidden bg-navy-800 text-white">
    <x-triangle class="pointer-events-none absolute top-1/2 -right-[10%] -z-10 w-[60rem] -translate-y-1/2 rotate-0 text-gold-300/15" />

    <div class="container-site grid items-center gap-12 py-20 md:py-28 lg:grid-cols-[1.2fr_1fr] lg:gap-20">
        <div>
            <p class="eyebrow mb-5" data-reveal>{{ __('home.admissions.eyebrow') }}</p>
            <h2 class="text-h2 text-white!" data-reveal>{{ __('home.admissions.title') }}</h2>
            <p class="mt-6 max-w-xl text-lead text-navy-100" data-reveal data-reveal-delay="0.1">{{ __('home.admissions.text') }}</p>
            <div class="mt-10 flex flex-wrap gap-4" data-reveal data-reveal-delay="0.2">
                <x-button :href="url('/admissions')" variant="gold" arrow>{{ __('home.admissions.primary') }}</x-button>
                <x-button :href="url('/admissions')" variant="outline">{{ __('home.admissions.secondary') }}</x-button>
            </div>
        </div>

        <div class="relative mx-auto hidden aspect-square w-full max-w-sm lg:block" aria-hidden="true" data-reveal="fade">
            <div class="absolute inset-0 rounded-full border border-gold-300/30"></div>
            <div class="absolute inset-8 rounded-full border border-gold-300/20"></div>
            <x-seal :size="384" class="absolute inset-14 size-[calc(100%-7rem)]" />
        </div>
    </div>
</section>
