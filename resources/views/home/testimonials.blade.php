{{-- Témoignages : une grande citation à la fois (piste scroll-snap utilisable sans JS, boutons et compteur accessibles, pas de lecture automatique) ; les mots de la citation arrivent un à un (home.js). --}}
<section class="home-voices on-dark" data-home="voices" aria-roledescription="carrousel" aria-labelledby="testimonials-title" x-data="carousel">
    <span class="home-voices__mark" aria-hidden="true">«</span>

    <div class="container-site">
        <header class="home-voices__head">
            <div>
                <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ __('home.testimonials.eyebrow') }}</p>
                <h2 id="testimonials-title" class="home-voices__title" data-reveal>{{ __('home.testimonials.title') }}</h2>
            </div>

            @if ($testimonials->count() > 1)
                <div class="flex items-center gap-3" role="group" aria-label="{{ __('Commandes du carrousel') }}">
                    <button type="button" @click="prev" x-bind:disabled="atStart" class="inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 disabled:opacity-30" aria-label="{{ __('Témoignage précédent') }}"><x-ico name="arrow-left" class="size-5" /></button>
                    <span class="min-w-14 text-center text-sm tabular-nums text-navy-100" x-text="label" aria-live="polite">1 / {{ $testimonials->count() }}</span>
                    <button type="button" @click="next" x-bind:disabled="atEnd" class="inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 disabled:opacity-30" aria-label="{{ __('Témoignage suivant') }}"><x-ico name="arrow-right" class="size-5" /></button>
                </div>
            @endif
        </header>

        <ul x-ref="track" class="home-voices__track flex snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" tabindex="0" aria-label="{{ __('Témoignages') }}">
            @foreach ($testimonials as $testimonial)
                {{-- Le rôle « groupe » est porté par le contenu, pas par l'élément de liste (une liste ne contient que des éléments de liste). --}}
                <li class="w-full shrink-0 snap-start pr-2 lg:pr-24">
                    <div role="group" aria-roledescription="diapositive" aria-label="{{ __(':iteration sur :v', ['iteration' => $loop->iteration, 'v' => $testimonials->count()]) }}" data-home-quote>
                        <x-testimonial-card :testimonial="$testimonial" />
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
