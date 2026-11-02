{{-- Admissions : le sceau au centre de deux anneaux, la devise tourne autour ; il s'incline vers le pointeur (animations : home.js). --}}
<section class="home-adm on-dark" data-home="adm" aria-labelledby="admissions-title">
    <div class="container-site home-adm__inner">
        <div>
            <p class="eyebrow mb-5 text-sun-400!" data-reveal>{{ __('home.admissions.eyebrow') }}</p>
            <h2 id="admissions-title" class="home-adm__title" data-reveal>{{ __('home.admissions.title') }}</h2>
            <p class="mt-6 max-w-xl text-lead text-navy-100" data-reveal data-reveal-delay="0.1">{{ __('home.admissions.text') }}</p>
            <div class="mt-10 flex flex-wrap gap-4" data-reveal data-reveal-delay="0.2">
                <x-button :href="config('uaz.registration_url')" target="_blank" rel="noopener noreferrer" variant="gold" arrow data-home-magnet>{{ __('home.admissions.primary') }}</x-button>
                <x-button :href="url('/admissions/licence')" variant="outline" data-home-magnet>{{ __('home.admissions.secondary') }}</x-button>
            </div>
        </div>

        <div class="home-seal" aria-hidden="true" data-home-seal>
            <svg viewBox="0 0 400 400" focusable="false">
                <defs><path id="home-seal-circle" d="M200 200 m-168 0 a168 168 0 1 1 336 0 a168 168 0 1 1 -336 0" /></defs>
                <circle class="home-seal__ring" cx="200" cy="200" r="190" data-home-ring />
                <circle class="home-seal__ring is-dashed" cx="200" cy="200" r="150" />
                <g class="home-seal__text" data-home-spin><text><textPath href="#home-seal-circle" startOffset="0">SPIRITUS · MANUS · MENS · SPIRITUS · MANUS · MENS ·</textPath></text></g>
            </svg>
            <x-seal :size="384" class="home-seal__img" data-home-seal-img />
        </div>
    </div>
</section>
