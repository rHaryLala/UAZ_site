{{--
    Héros plein écran. Le titre, le sous-titre et les boutons sont dans le HTML ; GSAP révèle le titre mot par mot
    (SplitText), dessine le triangle du sceau et applique une légère parallaxe (animations/hero.js).
--}}
<section data-hero class="on-dark relative isolate flex min-h-[100svh] flex-col justify-end overflow-hidden bg-navy-950 text-white" aria-labelledby="hero-title">
    {{-- Image d'en-tête (réglages du site) en parallaxe ; à défaut, fond marine et motifs du sceau --}}
    @if ($site->homeHeroImage)
        <div class="absolute inset-0 -z-30 overflow-hidden" data-hero-bg>
            <x-picture :asset="$site->homeHeroImage" conversion="hero" sizes="100vw" decorative eager class="size-full scale-110 object-cover" />
        </div>
        <div class="absolute inset-0 -z-20 bg-gradient-to-t from-navy-950/95 via-navy-950/35 to-navy-950/10" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-20 bg-gradient-to-r from-navy-950/75 via-navy-950/25 to-transparent" aria-hidden="true"></div>
    @else
        <div class="absolute inset-0 -z-30 bg-[radial-gradient(120%_90%_at_75%_30%,var(--color-navy-700)_0%,var(--color-navy-900)_55%,var(--color-navy-950)_100%)]" aria-hidden="true"></div>
    @endif

    {{-- Composition graphique : triangle + Madagascar + Spiritus · Manus · Mens (décorative) --}}
    <div class="pointer-events-none absolute inset-y-0 right-0 -z-10 flex w-full items-center justify-end overflow-hidden lg:w-[46%]" aria-hidden="true" data-hero-graphic>
        <div class="relative aspect-[100/90] w-[min(34rem,115vw)] translate-x-[22%] opacity-25 sm:translate-x-[10%] sm:opacity-60 lg:translate-x-[-4%] lg:opacity-100">
            <svg viewBox="0 0 100 90" class="absolute inset-0 size-full overflow-visible" fill="none">
                <path d="M3 4h94L50 86z" pathLength="1" stroke="var(--color-gold-300)" stroke-width="0.5" stroke-linejoin="miter" stroke-dasharray="1" stroke-dashoffset="0" data-hero-triangle />
                <path d="M3 4h94L50 86z" fill="var(--color-navy-800)" fill-opacity="0.35" data-hero-fill />
            </svg>
            <x-madagascar class="absolute top-[14%] left-[35%] h-[60%] text-gold-300/85" data-hero-island />
            <span class="absolute top-[4%] left-1/2 -translate-x-1/2 -translate-y-[160%] font-serif text-[0.7rem] tracking-[0.4em] text-gold-300 uppercase sm:text-sm max-sm:hidden" data-hero-word>Spiritus</span>
            <span class="absolute top-[30%] left-[10%] -rotate-[58deg] font-serif text-[0.7rem] tracking-[0.4em] text-gold-300 uppercase sm:text-sm max-sm:hidden" data-hero-word>Manus</span>
            <span class="absolute top-[30%] right-[10%] rotate-[58deg] font-serif text-[0.7rem] tracking-[0.4em] text-gold-300 uppercase sm:text-sm max-sm:hidden" data-hero-word>Mens</span>
            <span class="absolute top-[60%] left-1/2 -translate-x-1/2 font-serif text-2xl text-white sm:text-4xl" data-hero-word>{{ __('home.pillars.service') }}</span>
        </div>
    </div>

    <div class="container-site relative pt-[calc(var(--header-h)+3rem)] pb-10 md:pb-14">
        <p class="eyebrow mb-6" data-reveal>{{ __('home.hero.eyebrow') }}</p>

        <h1 id="hero-title" class="max-w-4xl text-display text-white! lg:max-w-[50rem]" data-hero-title>{{ $site->home_hero_title ?: __('home.hero.title') }}</h1>

        <p class="mt-8 max-w-2xl text-lead text-navy-100" data-reveal data-reveal-delay="0.5">
            {{ $site->home_hero_subtitle ?: __('home.hero.subtitle') }}
        </p>

        <div class="mt-10 flex flex-wrap gap-4" data-reveal data-reveal-delay="0.65">
            <x-button :href="url('/a-propos')" variant="gold" arrow>{{ __('home.hero.cta_primary') }}</x-button>
            <x-button :href="url('/contact')" variant="outline">{{ __('home.hero.cta_secondary') }}</x-button>
        </div>

        <div class="mt-14 flex items-end justify-between gap-6 border-t border-white/15 pt-5 text-sm text-navy-100" data-reveal data-reveal-delay="0.8">
            <p class="flex max-w-md items-center gap-3"><span class="size-2 shrink-0 rounded-full bg-gold-300" aria-hidden="true"></span>{{ __('home.hero.note') }}</p>
            <a href="#difference" class="hidden items-center gap-2 font-medium text-white hover:text-gold-300 sm:inline-flex">
                {{ __('home.hero.scroll') }} <x-ico name="chevron-down" class="size-4 animate-bounce motion-reduce:animate-none" />
            </a>
        </div>
    </div>
</section>
