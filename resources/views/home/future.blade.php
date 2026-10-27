@php
    $videoReady = is_file(public_path('videos/futur-960.mp4'));
    $poster = is_file(public_path('videos/futur-poster.webp')) ? asset('videos/futur-poster.webp') : null;
@endphp

{{-- « Votre futur commence ici » : grand message à gauche, vidéo verticale (4:5) à droite, sur fond bleu. --}}
<section class="on-dark relative isolate overflow-hidden bg-[radial-gradient(ellipse_80%_90%_at_50%_30%,var(--color-navy-700)_0%,var(--color-navy-800)_45%,var(--color-navy-900)_100%)] py-16 text-white md:py-24" aria-labelledby="future-title">
    <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(80rem,150vw)] -translate-x-1/2 -translate-y-1/2 text-gold-300/10" />

    <div class="container-site">
        <div class="mx-auto grid max-w-5xl overflow-hidden shadow-lift md:grid-cols-[1.1fr_1fr]" data-reveal>
            {{-- Message --}}
            <div class="flex flex-col justify-between bg-white p-8 text-navy-900 sm:p-12 md:p-14">
                <div>
                    <span class="mb-8 block h-1.5 w-14 bg-sun-400" aria-hidden="true"></span>
                    <h2 id="future-title" class="font-sans text-[clamp(2.6rem,1.6rem+4.4vw,5rem)] leading-[1.02] font-bold tracking-[-0.025em] text-navy-900!">{{ __('home.future.title') }}</h2>
                </div>
                <p class="mt-10 max-w-xs text-ink-700">{{ __('home.future.text') }}</p>
            </div>

            {{-- Vidéo verticale : chargée seulement au clic (aucun téléchargement tant qu'on ne lit pas) --}}
            <div class="relative aspect-[4/5] bg-navy-950" data-future>
                <video
                    data-future-video
                    data-src-hd="{{ asset('videos/futur-960.mp4') }}"
                    data-src-sd="{{ asset('videos/futur-640.mp4') }}"
                    @if ($poster) poster="{{ $poster }}" @endif
                    class="absolute inset-0 size-full object-cover"
                    preload="none" playsinline
                    aria-label="{{ __('home.future.video_label') }}"
                ></video>
                @if ($videoReady)
                    <button type="button" data-future-play class="group absolute inset-0 flex items-center justify-center bg-navy-950/25 transition hover:bg-navy-950/10 focus-visible:outline-offset-[-6px]" aria-label="{{ __('home.future.play') }}">
                        <span class="flex size-20 items-center justify-center bg-sun-400 text-night-900 shadow-lift transition-transform duration-300 group-hover:scale-110 sm:size-24">
                            <x-ico name="play" class="size-9 translate-x-0.5 sm:size-10" />
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</section>
