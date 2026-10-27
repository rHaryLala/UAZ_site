@php
    $hasCoords = filled($site->latitude) && filled($site->longitude);
    $osmEmbed = $hasCoords
        ? 'https://www.openstreetmap.org/export/embed.html?bbox='.($site->longitude - 0.01).'%2C'.($site->latitude - 0.006).'%2C'.($site->longitude + 0.01).'%2C'.($site->latitude + 0.006).'&layer=mapnik&marker='.$site->latitude.'%2C'.$site->longitude
        : null;
    $osmLink = $hasCoords
        ? 'https://www.openstreetmap.org/?mlat='.$site->latitude.'&mlon='.$site->longitude.'#map=15/'.$site->latitude.'/'.$site->longitude
        : 'https://www.openstreetmap.org/search?query='.urlencode((string) $site->address);

    // Le sujet arrive pré-rempli depuis les liens du site (?sujet=…) : l'accueil de la page s'adapte, sans rien promettre de plus.
    $subject = trim((string) request()->query('sujet', ''));
    $support = (bool) preg_match('/soutenir|donner|\bdon\b/iu', $subject);
    $copy = $support
        ? [
            'eyebrow' => 'Donner · Soutenir',
            'title' => 'Soutenir l\'UAZ',
            'intro' => 'Vous souhaitez accompagner l\'Université Adventiste Zurcher et ses étudiants ? Écrivez-nous : notre équipe vous répondra pour échanger avec vous.',
            'side' => 'Parlons de votre soutien',
        ]
        : [
            'eyebrow' => 'Contact',
            'title' => 'Contactez-nous',
            'intro' => 'Une question sur nos programmes, nos admissions ou la vie étudiante ? Notre équipe est à votre écoute pour vous accompagner.',
            'side' => 'Nos coordonnées',
        ];
    $image = $support ? $site->formationsHeroImage : ($site->homeHeroImage ?? $site->homeCtaImage);
@endphp

<x-layouts.app :title="$copy['title']" description="Contactez l'Université Adventiste Zurcher : admissions, formations, vie étudiante, soutien.">
    {{-- Héros photo, la carte du formulaire le chevauche --}}
    <header class="on-dark relative isolate overflow-hidden bg-navy-900 text-white">
        @if ($image)
            <x-picture :asset="$image" conversion="hero" sizes="100vw" decorative eager class="absolute inset-0 -z-20 size-full object-cover object-[50%_30%]" />
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-navy-950/90 via-navy-950/60 to-navy-950/15" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/70 to-transparent" aria-hidden="true"></div>

        <div class="container-site pt-12 pb-56 md:pt-16 md:pb-64">
            <x-breadcrumbs :items="[['label' => 'Contact']]" dark class="mb-10" />
            <p class="eyebrow mb-4 text-sun-400!" data-reveal>{{ $copy['eyebrow'] }}</p>
            <h1 class="max-w-3xl text-[clamp(2.5rem,1.3rem+4.4vw,5rem)] leading-[1.04] font-normal text-white!" data-reveal data-reveal-delay="0.1">{{ $copy['title'] }}</h1>
            <p class="mt-6 max-w-2xl text-lead text-white/85" data-reveal data-reveal-delay="0.2">{{ $copy['intro'] }}</p>
        </div>
    </header>

    <section class="relative bg-paper-100 pb-20 md:pb-28">
        <div class="container-site -mt-44 md:-mt-52">
            <div class="ornament relative grid bg-white shadow-lift lg:grid-cols-[0.8fr_1.2fr]" data-reveal>
                {{-- Coordonnées --}}
                <aside class="on-dark relative isolate overflow-hidden bg-navy-900 p-8 text-white md:p-12">
                    <x-madagascar class="pointer-events-none absolute -right-10 bottom-[-12%] -z-10 h-[75%] text-navy-800" />
                    <h2 class="font-serif text-2xl text-white md:text-3xl">{{ $copy['side'] }}</h2>
                    <span class="mt-5 block h-0.5 w-12 bg-sun-400" aria-hidden="true"></span>

                    <address class="mt-9 space-y-6 not-italic">
                        @if ($site->address)
                            <p class="flex gap-4"><x-ico name="map-pin" class="mt-1 size-5 text-sun-400" /><span>{{ $site->address }}</span></p>
                        @endif
                        @if ($site->email)
                            <p class="flex gap-4"><x-ico name="mail" class="mt-1 size-5 text-sun-400" /><a href="mailto:{{ $site->email }}" class="break-all underline decoration-sun-400 decoration-2 underline-offset-4 hover:text-sun-400">{{ $site->email }}</a></p>
                        @endif
                        @if ($site->phone)
                            <p class="flex gap-4"><x-ico name="phone" class="mt-1 size-5 text-sun-400" /><a href="tel:{{ preg_replace('/[^+\d]/', '', $site->phone) }}" class="underline decoration-sun-400 decoration-2 underline-offset-4 hover:text-sun-400">{{ $site->phone }}</a></p>
                        @endif
                        @if (! empty($site->opening_hours))
                            <div class="flex gap-4"><x-ico name="clock" class="mt-1 size-5 text-sun-400" />
                                <dl class="space-y-1.5">@foreach ($site->opening_hours as $line)<div><dt class="inline font-semibold">{{ $line['days'] }} :</dt> <dd class="inline text-navy-100">{{ $line['hours'] }}</dd></div>@endforeach</dl></div>
                        @endif
                    </address>

                    @php($socials = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'])
                    <ul class="mt-10 flex flex-wrap gap-3">
                        @foreach ($socials as $key => $label)
                            @if ($site->social($key))
                                <li><a href="{{ $site->social($key) }}" target="_blank" rel="noopener noreferrer" class="inline-flex size-11 items-center justify-center rounded-full border border-white/30 hover:bg-white hover:text-navy-900" aria-label="{{ $label }} (nouvel onglet)"><x-ico :name="$key" class="size-5" /></a></li>
                            @endif
                        @endforeach
                    </ul>
                </aside>

                {{-- Formulaire --}}
                <div class="p-8 md:p-12">
                    <h2 class="font-serif text-2xl text-navy-900 md:text-3xl">Écrivez-nous</h2>
                    <span class="mt-5 mb-9 block h-0.5 w-12 bg-sun-400" aria-hidden="true"></span>
                    <livewire:contact-form />
                </div>
            </div>
        </div>
    </section>

    {{-- Carte : chargée seulement au clic (vie privée et performance : aucun appel externe au chargement) --}}
    <section class="on-dark bg-navy-900 text-white" data-map>
        <div class="container-site py-10 md:py-12">
            @if ($osmEmbed)
                <details class="group">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 font-serif text-xl marker:hidden md:text-2xl [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-4"><x-ico name="map-pin" class="size-6 text-sun-400" /> Où nous trouver</span>
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-sun-400">Afficher la carte <x-ico name="chevron-down" class="size-5 transition-transform group-open:rotate-180" /></span>
                    </summary>
                    <iframe src="{{ $osmEmbed }}" title="Carte : emplacement du campus de l'UAZ" loading="lazy" referrerpolicy="no-referrer" class="mt-8 aspect-[16/7] w-full border-0 max-md:aspect-[4/3]"></iframe>
                </details>
            @else
                <p class="flex items-center gap-4 font-serif text-xl md:text-2xl"><x-ico name="map-pin" class="size-6 text-sun-400" /> {{ $site->address ?: 'Antsirabe, Madagascar' }}</p>
            @endif
            <a href="{{ $osmLink }}" target="_blank" rel="noopener noreferrer" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-sun-400 hover:text-white">
                Ouvrir dans OpenStreetMap <span class="sr-only">(nouvel onglet)</span><x-ico name="arrow-up-right" class="size-4" />
            </a>
        </div>
    </section>
</x-layouts.app>
