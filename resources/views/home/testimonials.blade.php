{{-- Carrousel de témoignages : piste scroll-snap (utilisable sans JS), boutons et compteur accessibles, pas de lecture automatique. --}}
<section class="on-dark relative isolate overflow-hidden bg-navy-800 py-20 text-white md:py-28" aria-roledescription="carrousel" aria-labelledby="testimonials-title" x-data="carousel">
    <x-triangle class="pointer-events-none absolute -top-1/4 -left-[10%] -z-10 w-[50rem] text-gold-300/10" />

    <div class="container-site">
        <header class="mb-10 flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="eyebrow mb-4" data-reveal>{{ __('home.testimonials.eyebrow') }}</p>
                <h2 id="testimonials-title" class="text-h2 text-white!" data-reveal>{{ __('home.testimonials.title') }}</h2>
            </div>

            @if ($testimonials->count() > 1)
                <div class="flex items-center gap-3" role="group" aria-label="{{ __('Commandes du carrousel') }}">
                    <button type="button" @click="prev" x-bind:disabled="atStart" class="inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 disabled:opacity-30" aria-label="{{ __('Témoignage précédent') }}"><x-ico name="arrow-left" class="size-5" /></button>
                    <span class="min-w-14 text-center text-sm tabular-nums text-navy-100" x-text="label" aria-live="polite">1 / {{ $testimonials->count() }}</span>
                    <button type="button" @click="next" x-bind:disabled="atEnd" class="inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 disabled:opacity-30" aria-label="{{ __('Témoignage suivant') }}"><x-ico name="arrow-right" class="size-5" /></button>
                </div>
            @endif
        </header>

        <ul x-ref="track" class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" tabindex="0" aria-label="{{ __('Témoignages') }}">
            @foreach ($testimonials as $testimonial)
                <li class="w-full shrink-0 snap-start pr-2 lg:pr-24" role="group" aria-roledescription="diapositive" aria-label="{{ __(':iteration sur :v', ['iteration' => $loop->iteration, 'v' => $testimonials->count()]) }}">
                    <x-testimonial-card :testimonial="$testimonial" />
                </li>
            @endforeach
        </ul>
    </div>
</section>
