{{--
    Héros d'après la maquette : photo du campus, titre serif, deux actions, bandeau d'information en bas.
    GSAP révèle le titre mot par mot (SplitText) et applique une légère parallaxe à la photo (animations/hero.js).
--}}
<section data-hero class="on-dark relative isolate flex min-h-[calc(100svh-var(--header-h)-2.25rem)] flex-col justify-between overflow-hidden bg-night-900 text-white" aria-labelledby="hero-title">
    @if ($site->homeHeroImage)
        <div class="absolute inset-0 -z-30 overflow-hidden" data-hero-bg>
            <x-picture :asset="$site->homeHeroImage" conversion="hero" sizes="100vw" decorative eager class="size-full scale-110 object-cover object-[50%_22%]" />
        </div>
        <div class="absolute inset-0 -z-20 bg-gradient-to-t from-night-900/70 via-night-900/5 to-transparent" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-20 bg-gradient-to-r from-night-900/75 via-night-900/25 to-transparent" aria-hidden="true"></div>
    @else
        <div class="absolute inset-0 -z-30 bg-[radial-gradient(120%_90%_at_75%_30%,var(--color-navy-700)_0%,var(--color-navy-900)_55%,var(--color-navy-950)_100%)]" aria-hidden="true"></div>
    @endif

    <div class="container-site relative pt-[clamp(2.5rem,7.5vw,6.75rem)]">
        <p class="eyebrow mb-5 text-[0.6875rem]! tracking-[0.03em]! text-sun-400!" data-reveal>{{ __('home.hero.eyebrow') }}</p>

        <h1 id="hero-title" class="max-w-[52rem] text-[clamp(2.5rem,1.2rem+4vw,4.15rem)] leading-[1.1] tracking-normal font-normal text-white!" data-hero-title>{{ $site->home_hero_title ?: __('home.hero.title') }}</h1>

        <p class="mt-8 max-w-[31rem] text-[clamp(1rem,0.9rem+0.3vw,1.0625rem)] leading-[1.6] text-white/85" data-reveal data-reveal-delay="0.5">
            {{ $site->home_hero_subtitle ?: __('home.hero.subtitle') }}
        </p>

        <div class="mt-9 flex flex-wrap items-center gap-x-7 gap-y-5" data-reveal data-reveal-delay="0.65">
            <x-button :href="url('/a-propos')" variant="sun" arrow class="btn-square">{{ __('home.hero.cta_primary') }}</x-button>
            <a href="{{ url('/contact') }}" class="group inline-flex items-center gap-5 py-2 text-[0.8125rem] font-semibold text-white hover:text-sun-400">
                {{ __('home.hero.cta_secondary') }}
                <x-ico name="arrow-right" class="size-4 text-sun-400 transition-transform group-hover:translate-x-1" />
            </a>
        </div>
    </div>

    <div class="container-site relative mt-14 pb-6 md:mt-20 md:pb-7" data-reveal data-reveal-delay="0.8">
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3 text-[0.75rem] text-white/85">
            <p class="flex max-w-xl items-center gap-3 text-[0.9375rem] font-medium"><span class="size-2 shrink-0 bg-sun-400" aria-hidden="true"></span>
                {{-- Trois versions qui défilent de bas en haut, 5 s chacune (animations : resources/js/hero-ticker.js) ; sans JavaScript, le français seul. --}}
                <span class="hero-ticker" data-hero-ticker>
                    <span lang="fr" data-state="current">{{ __('home.hero.note') }}</span>
                    <span lang="en" aria-hidden="true">{{ __('home.hero.note_en') }}</span>
                    <span lang="mg" aria-hidden="true">{{ __('home.hero.note_mg') }}</span>
                </span>
            </p>
            <p class="tracking-[0.02em] uppercase">{{ __('home.hero.region') }}</p>
        </div>
    </div>
</section>
