{{-- « Apprentissage pratique » : le titre défile en lettres géantes avec le défilement de la page (animations : home.js). --}}
@php($words = preg_split('/\s+/u', trim(__('home.practice.title'))))

<section class="home-practice" data-home="practice" aria-labelledby="practice-title">
    <x-madagascar class="home-practice__map" data-parallax="-12" />

    <div class="home-practice__belt" aria-hidden="true">
        <div class="home-practice__track" data-home-belt>
            @foreach (range(1, 2) as $copy)
                <p>@foreach ($words as $word)<span>{{ $word }}</span>@endforeach</p>
            @endforeach
        </div>
    </div>

    <div class="container-site home-practice__body">
        <p class="eyebrow mb-5" data-reveal>{{ __('home.practice.eyebrow') }}</p>
        <h2 id="practice-title" class="home-practice__title" data-reveal>{{ __('home.practice.title') }}</h2>
        <p class="mt-8 max-w-2xl text-lead text-ink-700" data-reveal data-reveal-delay="0.1">{{ __('home.practice.text') }}</p>
        <div class="mt-10" data-reveal data-reveal-delay="0.2">
            <x-button :href="url('/formations')" variant="navy" arrow data-home-magnet>{{ __('home.formations.link') }}</x-button>
        </div>
    </div>
</section>
