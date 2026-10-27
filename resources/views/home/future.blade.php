@php
    $videoReady = is_file(public_path('videos/futur-960.mp4'));
    $poster = is_file(public_path('videos/futur-poster.webp')) ? asset('videos/futur-poster.webp') : null;
@endphp

{{-- « Votre futur commence ici » : grand message à gauche, vidéo verticale (4:5) à droite, sur fond bleu. --}}
<section class="on-dark relative isolate overflow-hidden bg-[radial-gradient(ellipse_80%_90%_at_50%_30%,var(--color-navy-700)_0%,var(--color-navy-800)_45%,var(--color-navy-900)_100%)] py-16 text-white md:py-24" aria-labelledby="future-title">
    <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(80rem,150vw)] -translate-x-1/2 -translate-y-1/2 text-gold-300/10" />

    <div class="container-site">
        <div class="mx-auto grid max-w-5xl overflow-hidden shadow-lift md:grid-cols-[1.1fr_1fr]" data-reveal>
            {{-- Message : quatre lignes, en IBM Plex Sans gras --}}
            <div class="flex flex-col justify-center bg-white px-8 py-10 text-navy-900 sm:px-12 md:px-14">
                <h2 id="future-title" class="font-display text-[clamp(3rem,1.6rem+5.6vw,5.75rem)] leading-[1.04] font-bold tracking-[-0.02em] text-navy-900!">
                    @foreach (__('home.future.lines') as $line)<span class="block">{{ $line }}</span>@endforeach
                </h2>
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
                        <span class="flex h-9 w-14 items-center justify-center bg-sun-400 text-night-900 shadow-lift transition-transform duration-300 group-hover:scale-110 sm:h-10 sm:w-16">
                            <x-ico name="play" class="size-4 translate-x-px sm:size-5" />
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</section>
