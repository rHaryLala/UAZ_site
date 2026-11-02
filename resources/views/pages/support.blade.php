@php
    $site = \App\Models\SiteSetting::current();
    $donation = config('uaz.donation_url');
    $contact = url('/contact?sujet='.rawurlencode('Soutenir l\'UAZ'));
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    {{-- Héros photo --}}
    <header class="on-dark relative isolate overflow-hidden bg-navy-900 text-white">
        @if ($page->cover)
            <x-picture :asset="$page->cover" conversion="hero" sizes="100vw" decorative eager class="absolute inset-0 -z-20 size-full object-cover object-[50%_35%]" />
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-navy-950/92 via-navy-950/65 to-navy-950/10" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/70 via-transparent to-transparent" aria-hidden="true"></div>

        <div class="container-site flex min-h-[34rem] flex-col justify-end pt-10 pb-16 md:min-h-[40rem] md:pb-24">
            <x-breadcrumbs :items="[['label' => $page->title]]" dark class="mb-auto" />
            <p class="eyebrow mt-16 mb-4 text-sun-400!" data-reveal>{{ __('support.hero.eyebrow') }}</p>
            <h1 class="max-w-3xl text-[clamp(2.75rem,1.4rem+5vw,5.5rem)] leading-[1.03] font-normal text-white!" data-reveal data-reveal-delay="0.1">{{ $page->title }}</h1>
            <p class="mt-6 max-w-2xl text-lead text-white/85" data-reveal data-reveal-delay="0.2">{{ $page->summary ?: __('support.hero.lead') }}</p>
            <div class="mt-9 flex flex-wrap gap-4" data-reveal data-reveal-delay="0.3">
                @if ($donation)
                    <x-button :href="$donation" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square">{{ __('support.cta.donate') }}</x-button>
                @else
                    <x-button :href="$contact" variant="sun" arrow class="btn-square">{{ __('support.cta.contact') }}</x-button>
                @endif
                <x-button href="#projets" variant="outline">{{ __('support.cta.projects') }}</x-button>
            </div>
        </div>
    </header>

    {{-- Introduction --}}
    <section class="relative isolate overflow-hidden bg-paper-100 py-20 md:py-28">
        <x-madagascar class="pointer-events-none absolute top-6 -right-40 -z-10 h-[40rem] text-navy-800/[0.035]" />
        <div class="container-site grid gap-10 lg:grid-cols-[14rem_1fr] lg:gap-16">
            <p class="eyebrow lg:pt-3" data-reveal>{{ __('support.intro.eyebrow') }}</p>
            <p class="max-w-4xl font-serif text-[clamp(1.35rem,1rem+1.5vw,2.2rem)] leading-snug text-navy-900" data-reveal data-reveal-delay="0.1">{{ __('support.intro.text') }}</p>
        </div>
    </section>

    {{-- Projets --}}
    <section id="projets" class="bg-white py-20 md:py-28">
        <div class="container-site">
            <div class="mb-14 flex flex-wrap items-end justify-between gap-6 md:mb-20">
                <div>
                    <p class="eyebrow mb-4" data-reveal>{{ __('support.projects.eyebrow') }}</p>
                    <h2 class="text-h1" data-reveal data-reveal-delay="0.1">{{ __('support.projects.title') }}</h2>
                </div>
                <span class="hidden h-1 w-24 bg-sun-400 md:block" aria-hidden="true"></span>
            </div>
            <p class="-mt-6 mb-12 max-w-3xl text-lead text-ink-700 md:-mt-12 md:mb-16" data-reveal>{{ __('support.projects.lead') }}</p>

            @forelse ($projects as $project)
                <article class="group grid items-stretch gap-0 border border-navy-800/10 bg-paper-100 shadow-card lg:grid-cols-2 {{ $loop->last ? '' : 'mb-10 md:mb-14' }}" data-reveal>
                    <div @class(['relative min-h-72 overflow-hidden bg-navy-900 lg:min-h-[28rem]', 'lg:order-2' => $loop->even])>
                        @if ($project->cover)
                            <x-picture :asset="$project->cover" conversion="hero" sizes="(min-width: 1024px) 50vw, 100vw" decorative class="absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-105" />
                        @else
                            <x-madagascar class="absolute inset-y-6 right-6 h-[88%] text-navy-800" />
                        @endif
                        @if ($project->status_label)
                            <span class="absolute top-0 left-0 bg-sun-400 px-4 py-2 text-xs font-bold tracking-wider text-night-900 uppercase">{{ $project->status_label }}</span>
                        @endif
                    </div>

                    <div class="ornament relative flex flex-col justify-center p-8 md:p-12 lg:p-14">
                        <p class="eyebrow mb-4">Projet {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                        <h3 class="text-h2"><a href="{{ $project->url() }}" class="stretched-link hover:text-royal-600">{{ $project->title }}</a></h3>
                        @if ($project->summary)<p class="mt-5 text-lead text-ink-700">{{ $project->summary }}</p>@endif

                        @if ($project->location || $project->budget)
                            <dl class="mt-8 grid grid-cols-2 gap-6 border-t border-navy-800/10 pt-6">
                                @if ($project->location)<div><dt class="eyebrow mb-1 font-sans! text-[0.6875rem]!">Lieu</dt><dd class="font-serif text-lg text-navy-900">{{ $project->location }}</dd></div>@endif
                                @if ($project->budget)<div><dt class="eyebrow mb-1 font-sans! text-[0.6875rem]!">Budget prévu</dt><dd class="font-serif text-lg text-navy-900">{{ $project->budget }}</dd></div>@endif
                            </dl>
                        @endif

                        <span class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-royal-600" aria-hidden="true">{{ __('support.projects.more') }} <x-ico name="arrow-right" class="size-4 transition-transform group-hover:translate-x-1" /></span>
                    </div>
                </article>
            @empty
                <div class="ornament relative border border-navy-800/10 bg-paper-100 p-12 text-center">
                    <p class="font-serif text-2xl text-navy-900">{{ __('support.projects.empty') }}</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Comment donner --}}
    <section class="on-dark relative isolate overflow-hidden bg-navy-900 py-20 text-white md:py-28">
        <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(80rem,150vw)] -translate-x-1/2 -translate-y-1/2 text-gold-300/10" />
        <div class="container-site">
            <div class="mx-auto max-w-3xl text-center">
                <p class="eyebrow mb-4" data-reveal>{{ __('support.how.eyebrow') }}</p>
                <h2 class="text-h1 text-white!" data-reveal data-reveal-delay="0.1">{{ __('support.how.title') }}</h2>
            </div>

            <ul class="mx-auto mt-14 grid max-w-5xl gap-6 md:grid-cols-2">
                <li class="ornament relative flex flex-col border border-white/15 bg-white/5 p-9 md:p-11" data-reveal>
                    <x-ico name="heart" class="size-8 text-sun-400" />
                    <h3 class="mt-6 font-serif text-2xl text-white">{{ __('support.how.online.title') }}</h3>
                    @if ($donation)
                        <p class="mt-4 text-navy-100">{{ __('support.how.online.text') }}</p>
                        <x-button :href="$donation" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square mt-8 w-fit">{{ __('support.cta.donate') }}</x-button>
                    @else
                        <p class="mt-4 text-navy-100">{{ __('support.how.online.soon') }}</p>
                        <span class="mt-8 inline-flex w-fit items-center gap-2 border border-sun-400/50 px-4 py-2 text-xs font-bold tracking-wider text-sun-400 uppercase">{{ __('support.how.online.badge') }}</span>
                    @endif
                </li>
                <li class="ornament relative flex flex-col border border-white/15 bg-white/5 p-9 md:p-11" data-reveal data-reveal-delay="0.1">
                    <x-ico name="mail" class="size-8 text-sun-400" />
                    <h3 class="mt-6 font-serif text-2xl text-white">{{ __('support.how.contact.title') }}</h3>
                    <p class="mt-4 text-navy-100">{{ __('support.how.contact.text') }}</p>
                    @php($contacts = config('uaz.donation_contacts'))
                    <ul class="mt-6 space-y-2 text-sm">
                        @foreach ($contacts['phones'] ?? [] as $phone)
                            <li class="flex items-center gap-3"><x-ico name="phone" class="size-4 text-sun-400" /><a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}" class="hover:text-sun-400">{{ $phone }}</a></li>
                        @endforeach
                        @foreach ($contacts['emails'] ?? [] as $mail)
                            <li class="flex items-center gap-3"><x-ico name="mail" class="size-4 text-sun-400" /><a href="mailto:{{ $mail }}" class="break-all hover:text-sun-400">{{ $mail }}</a></li>
                        @endforeach
                    </ul>
                    <x-button :href="$contact" variant="outline" arrow class="btn-square mt-8 w-fit">{{ __('support.cta.contact') }}</x-button>
                </li>
            </ul>
        </div>
    </section>
</x-layouts.app>
