{{-- Fermeture : la photo du campus glisse derrière un grand titre dont les mots arrivent un à un ; les boutons sont attirés par le pointeur (animations : home.js). --}}
<section class="home-close on-dark" data-home="close" aria-labelledby="home-close-title">
    @if ($site->homeCtaImage)
        <div class="home-close__bg" data-home-close-bg aria-hidden="true">
            <x-picture :asset="$site->homeCtaImage" conversion="hero" sizes="100vw" decorative class="size-full object-cover object-[50%_72%]" />
        </div>
    @endif
    <div class="home-close__shade" aria-hidden="true"></div>

    <div class="container-site home-close__body">
        <p class="eyebrow mb-6 text-sun-400!" data-reveal>{{ __('home.cta.eyebrow') }}</p>
        <h2 id="home-close-title" class="home-close__title" data-home-close-title>{{ __('home.cta.title') }}</h2>
        <p class="mx-auto mt-6 max-w-2xl text-lead text-navy-100" data-reveal data-reveal-delay="0.1">{{ __('home.cta.text') }}</p>
        <div class="mt-10 flex flex-wrap justify-center gap-4" data-reveal data-reveal-delay="0.2">
            <x-button :href="config('uaz.registration_url')" target="_blank" rel="noopener noreferrer" variant="gold" arrow data-home-magnet>{{ __('home.admissions.primary') }}</x-button>
            <x-button :href="url('/admissions/licence')" variant="outline" data-home-magnet>{{ __('home.admissions.secondary') }}</x-button>
        </div>
    </div>
</section>
