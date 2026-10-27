@php
    $site = \App\Models\SiteSetting::current();
    $figures = $site->key_figures ?? [];
    $videoDir = public_path('videos');
    $hasVideo = is_file($videoDir.'/campus-1080.mp4');
    $poster = is_file($videoDir.'/campus-poster.webp') ? asset('videos/campus-poster.webp') : null;
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- 1. Héros vidéo : le drone survole le campus (lecture une fois, puis arrêt sur la dernière image) --}}
    <header class="on-dark relative isolate flex min-h-[calc(100svh-var(--header-h)-2.25rem)] flex-col justify-end overflow-hidden bg-night-900 text-white md:min-h-[88svh]">
        @if ($hasVideo)
            <video
                data-hero-video
                data-src-hd="{{ asset('videos/campus-1080.mp4') }}"
                data-src-sd="{{ asset('videos/campus-720.mp4') }}"
                @if ($poster) poster="{{ $poster }}" @endif
                class="absolute inset-0 -z-20 size-full object-cover"
                muted playsinline preload="none" disablepictureinpicture tabindex="-1" aria-hidden="true"
            ></video>
        @elseif ($site->homeHeroImage)
            <x-picture :asset="$site->homeHeroImage" conversion="hero" sizes="100vw" decorative eager class="absolute inset-0 -z-20 size-full object-cover" />
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-night-900/90 via-night-900/25 to-night-900/30" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-night-900/60 via-transparent to-transparent" aria-hidden="true"></div>

        <div class="container-site pt-10">
            <x-breadcrumbs :items="[['label' => $page->title]]" dark />
        </div>

        <div class="container-site relative mt-auto pt-24 pb-14 md:pb-20">
            <p class="eyebrow mb-5 text-sun-400!" data-reveal>{{ __('about.hero.eyebrow') }}</p>
            <h1 class="max-w-4xl text-[clamp(2.75rem,1.4rem+5.4vw,6rem)] leading-[1.02] font-normal text-white!" data-reveal data-reveal-delay="0.1">{{ __('about.hero.title') }}</h1>
            <p class="mt-7 max-w-2xl text-lead text-white/85" data-reveal data-reveal-delay="0.25">{{ __('about.hero.lead') }}</p>
            <a href="#mission" class="group mt-10 inline-flex items-center gap-4 text-sm font-semibold tracking-wide uppercase hover:text-sun-400" data-reveal data-reveal-delay="0.4">
                <span class="relative block h-12 w-px overflow-hidden bg-white/30"><span class="absolute inset-x-0 top-0 h-1/2 animate-[scroll-cue_1.8s_ease-in-out_infinite] bg-sun-400 motion-reduce:animate-none"></span></span>
                {{ __('about.hero.scroll') }}
            </a>
        </div>
    </header>

    {{-- 2. Mission, vision, valeurs --}}
    <section id="mission" class="relative isolate overflow-hidden bg-paper-100 py-20 md:py-32">
        <x-madagascar class="pointer-events-none absolute top-10 -right-40 -z-10 h-[46rem] text-navy-800/[0.035]" />
        <div class="container-site">
            <div class="grid gap-10 lg:grid-cols-[14rem_1fr] lg:gap-16">
                <p class="eyebrow lg:pt-4" data-reveal>{{ __('about.mission.eyebrow') }}</p>
                <p class="max-w-4xl font-serif text-[clamp(1.4rem,1rem+1.6vw,2.35rem)] leading-snug text-navy-900" data-reveal data-reveal-delay="0.1">{{ __('about.mission.text') }}</p>
            </div>

            <div class="mt-16 grid gap-6 lg:mt-24 lg:grid-cols-[1.1fr_1fr] lg:gap-8">
                <div class="on-dark ornament relative flex min-h-72 flex-col justify-between bg-navy-900 p-9 text-white md:p-12" data-reveal>
                    <p class="eyebrow">{{ __('about.mission.vision.eyebrow') }}</p>
                    <p class="mt-10 font-serif text-[clamp(1.9rem,1.2rem+2.6vw,3.4rem)] leading-[1.1] text-white">{{ __('about.mission.vision.text') }}</p>
                </div>
                <div class="relative border border-navy-800/15 bg-white p-9 md:p-12" data-reveal data-reveal-delay="0.1">
                    <p class="eyebrow mb-8">{{ __('about.mission.values.eyebrow') }}</p>
                    <ol class="space-y-6">
                        @foreach (__('about.mission.values.items') as $value)
                            <li class="flex items-baseline gap-5 border-b border-navy-800/10 pb-6 last:border-0 last:pb-0">
                                <span class="font-serif text-2xl text-gold-700" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="font-serif text-xl leading-snug text-navy-900 md:text-2xl">{{ $value }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </section>

    {{-- 3. Histoire : trois temps --}}
    <section class="relative bg-white py-20 md:py-28">
        <div class="container-site">
            <div class="mb-14 flex flex-wrap items-end justify-between gap-6 md:mb-20">
                <div>
                    <p class="eyebrow mb-4" data-reveal>{{ __('about.history.eyebrow') }}</p>
                    <h2 class="text-h1" data-reveal data-reveal-delay="0.1">{{ __('about.history.title') }}</h2>
                </div>
                <span class="hidden h-1 w-24 bg-sun-400 md:block" aria-hidden="true"></span>
            </div>

            <ol class="relative grid gap-12 lg:grid-cols-3 lg:gap-10">
                <span class="absolute top-[1.1rem] right-0 left-0 hidden h-px bg-navy-800/20 lg:block" aria-hidden="true"></span>
                @foreach (__('about.history.steps') as $step)
                    <li class="relative lg:pt-14" data-reveal data-reveal-delay="{{ $loop->index * 0.12 }}">
                        <span class="absolute top-2.5 left-0 hidden size-3.5 rotate-45 bg-sun-400 lg:block" aria-hidden="true"></span>
                        <p class="font-serif text-5xl leading-none text-navy-800 md:text-6xl">{{ $step['label'] }}</p>
                        <h3 class="mt-5 text-h3">{{ $step['title'] }}</h3>
                        <p class="mt-3 max-w-sm text-ink-700">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 4. Chiffres clés --}}
    @if (count($figures))
        <section class="on-dark relative isolate overflow-hidden bg-navy-900 py-16 text-white md:py-24">
            <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(80rem,150vw)] -translate-x-1/2 -translate-y-1/2 text-gold-300/10" />
            <div class="container-site"><x-key-figures :figures="$figures" tone="dark" /></div>
        </section>
    @endif

    {{-- 5. Campus --}}
    <section class="relative bg-paper-100">
        <div class="grid lg:grid-cols-2">
            <div class="relative min-h-[26rem] overflow-hidden bg-navy-900 lg:min-h-[44rem]">
                @if ($site->homeHeroImage)
                    <x-picture :asset="$site->homeHeroImage" conversion="hero" sizes="(min-width: 1024px) 50vw, 100vw" decorative class="absolute inset-0 size-full object-cover" data-parallax />
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-night-900/70 via-transparent to-transparent" aria-hidden="true"></div>
                <div class="on-dark absolute bottom-0 left-0 p-8 text-white md:p-12">
                    <p class="font-serif text-[clamp(4rem,2.5rem+6vw,8rem)] leading-none">200<span class="text-sun-400">+</span></p>
                    <p class="mt-2 text-sm font-semibold tracking-[0.2em] uppercase">hectares de campus</p>
                </div>
            </div>

            <div class="flex flex-col justify-center px-[var(--gutter)] py-16 md:py-24 lg:px-16 xl:px-24">
                <p class="eyebrow mb-4" data-reveal>{{ __('about.campus.eyebrow') }}</p>
                <h2 class="text-h2" data-reveal data-reveal-delay="0.1">{{ __('about.campus.title') }}</h2>
                <p class="mt-6 text-lead text-ink-700" data-reveal data-reveal-delay="0.15">{{ __('about.campus.text') }}</p>

                <ul class="mt-10 space-y-7">
                    @foreach (__('about.campus.points') as $point)
                        <li class="border-l-2 border-sun-400 pl-6" data-reveal data-reveal-delay="{{ 0.2 + $loop->index * 0.1 }}">
                            <h3 class="font-serif text-xl text-navy-900">{{ $point['title'] }}</h3>
                            <p class="mt-2 text-ink-700">{{ $point['text'] }}</p>
                        </li>
                    @endforeach
                </ul>

                <a href="https://www.youtube.com/watch?v=fmiHN-BsL-Q" target="_blank" rel="noopener noreferrer" class="link-arrow mt-10 w-fit">{{ __('about.campus.video') }} <x-ico name="arrow-up-right" class="size-4" /><span class="sr-only"> (nouvel onglet)</span></a>
            </div>
        </div>
    </section>

    {{-- 6. Parcours --}}
    <section class="relative isolate overflow-hidden bg-white py-20 md:py-28">
        <div class="container-site">
            <div class="mx-auto max-w-3xl text-center">
                <p class="eyebrow mb-4" data-reveal>{{ __('about.paths.eyebrow') }}</p>
                <h2 class="text-h1" data-reveal data-reveal-delay="0.1">{{ __('about.paths.title') }}</h2>
                <p class="mt-6 text-lead text-ink-700" data-reveal data-reveal-delay="0.15">{{ __('about.paths.text') }}</p>
            </div>

            <ul class="mt-14 grid gap-5 md:mt-20 md:grid-cols-3">
                @foreach (__('about.paths.items') as $path)
                    <li data-reveal data-reveal-delay="{{ $loop->index * 0.1 }}">
                        <article class="ornament group relative flex h-full flex-col border border-navy-800/10 bg-paper-100 p-9 transition duration-300 hover:-translate-y-1 hover:bg-navy-900 hover:text-white hover:shadow-lift md:p-10">
                            <p class="eyebrow transition-colors group-hover:text-sun-400">{{ $path['tag'] }}</p>
                            <h3 class="mt-6 font-serif text-2xl leading-snug text-navy-900 transition-colors group-hover:text-white md:text-[1.7rem]">{{ $path['title'] }}</h3>
                            <p class="mt-4 text-ink-700 transition-colors group-hover:text-navy-100">{{ $path['text'] }}</p>
                            <span class="mt-auto pt-10 font-serif text-6xl leading-none text-navy-800/10 transition-colors group-hover:text-sun-400/40" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        </article>
                    </li>
                @endforeach
            </ul>

            <div class="mt-12 flex flex-col items-center gap-6 text-center" data-reveal>
                <p class="max-w-2xl font-serif text-xl text-navy-800">{{ __('about.paths.closing') }}</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <x-button :href="url('/formations')" variant="navy" arrow>{{ __('about.paths.link') }}</x-button>
                    <x-button :href="url('/admissions/telechargements')" variant="outline">{{ __('about.paths.curriculum') }}</x-button>
                </div>
            </div>
        </div>
    </section>

    {{-- 7. Retour d'expérience --}}
    <section class="on-dark relative isolate overflow-hidden bg-navy-800 py-20 text-white md:py-32">
        <x-madagascar class="pointer-events-none absolute top-1/2 -left-24 -z-10 h-[42rem] -translate-y-1/2 text-navy-700/40" />
        <div class="container-site">
            <div class="mx-auto max-w-4xl">
                <span class="block font-serif text-[8rem] leading-[0.6] text-sun-400" aria-hidden="true" data-reveal>“</span>
                <p class="eyebrow mt-6 mb-6" data-reveal>{{ __('about.story.eyebrow') }}</p>
                <blockquote>
                    <p class="font-serif text-[clamp(1.5rem,1rem+2vw,2.6rem)] leading-snug text-white" data-reveal data-reveal-delay="0.1">{{ __('about.story.quote') }}</p>
                    <p class="mt-8 max-w-3xl text-lead text-navy-100" data-reveal data-reveal-delay="0.2">{{ __('about.story.more') }}</p>
                </blockquote>
            </div>
        </div>
    </section>

    {{-- 8. Prochaines dates --}}
    @if ($events->isNotEmpty())
        <section class="bg-paper-100 py-20 md:py-28">
            <div class="container-site">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p class="eyebrow mb-4" data-reveal>{{ __('about.dates.eyebrow') }}</p>
                        <h2 class="text-h1" data-reveal data-reveal-delay="0.1">{{ __('about.dates.title') }}</h2>
                    </div>
                    <a href="{{ url('/evenements') }}" class="link-arrow">{{ __('about.dates.all') }} <x-ico name="arrow-right" class="size-4" /></a>
                </div>
                <ul class="grid gap-6 lg:grid-cols-3">
                    @foreach ($events as $event)
                        <li data-reveal data-reveal-delay="{{ $loop->index * 0.08 }}"><x-event-card :event="$event" class="h-full" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-cta-banner
        :image="$site->homeCtaImage"
        :eyebrow="__('home.cta.eyebrow')"
        :title="__('home.cta.title')"
        :text="__('home.cta.text')"
        :primary="['label' => __('home.admissions.primary'), 'url' => config('uaz.registration_url')]"
        :secondary="['label' => __('home.admissions.secondary'), 'url' => url('/admissions/licence')]"
    />
</x-layouts.app>
