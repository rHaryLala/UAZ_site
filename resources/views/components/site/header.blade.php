@php
    $socials = [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
    ];
    // Le dernier lien de la barre du haut est mis en avant (« Se connecter » dans la maquette).
    $topbarLinks = collect($topbarMenu);
    $topbarCta = $topbarLinks->count() > 1 ? $topbarLinks->last() : null;
    $topbarPlain = $topbarCta ? $topbarLinks->slice(0, -1) : $topbarLinks;
    // Dans le menu plein écran, on n'affiche pas deux fois la même page.
    // Les rubriques à choix (« Étudiant ») sont dépliées en leurs liens, sans le détail après « — ».
    $spaces = $topbarLinks->flatMap(fn ($link) => count($link['children'])
        ? collect($link['children'])->map(fn ($child) => ['label' => explode(' — ', $child['label'])[0]] + $child)->all()
        : [$link])->reject(fn ($link) => collect($footerMenu)->pluck('url')->contains($link['url']));
@endphp

<div x-data="siteMenu" @keydown.escape.window="close" class="contents">
    {{-- Barre du haut : devise et liens secondaires (menu « topbar » éditable) --}}
    <div class="topbar text-[0.6875rem]" role="region" aria-label="Devise et liens secondaires">
        <div class="container-site flex min-h-9 items-center justify-between gap-6">
            <p class="tracking-[0.01em] text-sun-400 uppercase">Spiritus · Manus · Mens — Fondée en {{ config("uaz.founded_year") }}.</p>
            <nav aria-label="Liens secondaires" class="hidden items-center gap-6 md:flex">
                @foreach ($topbarPlain as $item)
                    @if (count($item['children']))
                        {{-- Rubrique à choix (ex. « Étudiant » : Registrar ou Moodle) : le titre ouvre la liste, il n'est pas un lien. --}}
                        <div class="group relative">
                            <button type="button" aria-haspopup="true" class="inline-flex cursor-default items-center gap-1 py-2 transition-colors group-hover:text-sun-400 group-focus-within:text-sun-400">
                                {{ $item['label'] }}
                                <x-ico name="chevron-down" class="size-3 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" />
                            </button>
                            <ul class="invisible absolute top-full right-0 z-[70] w-64 translate-y-1 bg-white p-2 text-ink-800 opacity-0 shadow-lift transition duration-200 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                @foreach ($item['children'] as $child)
                                    @php([$name, $hint] = array_pad(explode(' — ', $child['label'], 2), 2, null))
                                    <li>
                                        <a href="{{ $child['url'] }}" class="block px-4 py-3 text-ink-800 hover:bg-mist-100 hover:text-royal-600!" @if ($child['new_tab']) target="_blank" rel="noopener noreferrer" @endif>
                                            <span class="block text-sm font-semibold">{{ $name }}@if ($child['new_tab'])<span class="sr-only"> (nouvel onglet)</span>@endif</span>
                                            @if ($hint)<span class="mt-0.5 block text-xs text-ink-600">{{ $hint }}</span>@endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($item['url'] === '#newsletter')
                        {{-- « S'abonner » : ouvre la fenêtre de la lettre d'information (resources/js/newsletter.js). --}}
                        <button type="button" data-newsletter-open aria-haspopup="dialog" class="py-2">{{ $item['label'] }}</button>
                    @else
                        <a href="{{ $item['url'] }}" class="py-2" @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                    @endif
                @endforeach
                @if ($topbarCta && count($topbarCta['children']))
                    {{-- Pastille à choix (« Étudiant ») : ouvre une fenêtre pour choisir où aller (resources/js/student-dialog.js). --}}
                    <button type="button" data-student-open aria-haspopup="dialog" class="rounded-full bg-sun-400 px-3 py-1 font-semibold text-night-900 hover:bg-sun-300">{{ $topbarCta['label'] }}</button>
                @elseif ($topbarCta)
                    <a href="{{ $topbarCta['url'] }}" class="rounded-full bg-sun-400 px-3 py-1 font-semibold text-night-900 hover:text-night-900! hover:bg-sun-300" @if ($topbarCta['new_tab']) target="_blank" rel="noopener noreferrer" @endif>{{ $topbarCta['label'] }}</a>
                @endif
            </nav>
        </div>
    </div>

    <header x-bind:data-scrolled="scrolled" x-bind:data-dark="dark" class="site-header sticky top-0 z-50">
        <div class="container-site flex h-[var(--header-h)] items-center justify-between gap-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3.5" aria-label="{{ $site->site_name }} — accueil">
                <x-seal :size="56" eager class="size-11 md:size-14" />
                <span class="leading-none">
                    {{-- « Université Adventiste » est justifié sur la largeur de « ZURCHER » (w-0 min-w-full : il ne l'élargit jamais). --}}
                    <span class="brand-tag block w-0 min-w-full text-justify [text-align-last:justify] text-[0.6rem] font-medium text-navy-900 uppercase md:text-[0.72rem]">Université Adventiste</span>
                    <span class="brand-name mt-1 block font-serif text-[1.3rem] font-bold text-royal-600 uppercase md:text-[1.55rem]">Zurcher</span>
                </span>
            </a>

            <div class="flex items-center gap-3 sm:gap-7">
                {{-- Navigation principale (grands écrans) --}}
                <nav aria-label="Navigation principale" class="hidden xl:block">
                    <ul class="flex items-center gap-7">
                        @foreach ($headerMenu as $item)
                            <li class="group relative">
                                @if (count($item['children']))
                                    {{-- Rubrique à sous-pages : pas de page « parent », le titre ouvre simplement la liste. --}}
                                    <button type="button" aria-haspopup="true" class="site-header__link inline-flex cursor-default items-center gap-1.5 py-2 text-[0.8125rem] font-medium whitespace-nowrap">
                                        {{ $item['label'] }}
                                        <x-ico name="chevron-down" class="size-3.5 opacity-70 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" />
                                    </button>
                                @else
                                    <a href="{{ $item['url'] }}" class="site-header__link inline-flex items-center gap-1.5 py-2 text-[0.8125rem] font-medium whitespace-nowrap"
                                       @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                       @if ($item['url'] === url('/recherche')) @click.prevent="openSearch" aria-haspopup="dialog" @endif
                                       @if (url()->current() === $item['url']) aria-current="page" @endif>
                                        {{ $item['label'] }}
                                    </a>
                                @endif
                                @if (count($item['children']))
                                    <ul class="invisible absolute top-full left-0 z-10 min-w-56 translate-y-2 bg-white p-2 opacity-0 shadow-lift transition duration-200 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                        @foreach ($item['children'] as $child)
                                            <li><a href="{{ $child['url'] }}" class="block px-4 py-2.5 text-sm font-medium text-ink-800 hover:bg-mist-100 hover:text-royal-600">{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
                <a href="{{ url('/recherche') }}" @click.prevent="openSearch" class="site-header__icon inline-flex size-11 items-center justify-center" aria-label="Rechercher sur le site" aria-haspopup="dialog">
                    <x-ico name="search" class="size-5" />
                </a>
                <x-button :href="config('uaz.registration_url')" target="_blank" rel="noopener noreferrer" variant="royal" arrow class="btn-square hidden md:inline-flex">S'inscrire</x-button>
                <button
                    type="button"
                    x-ref="trigger"
                    @click="toggle"
                    x-bind:aria-expanded="open"
                    aria-controls="site-menu"
                    class="site-header__icon inline-flex h-11 items-center gap-2.5 px-1 text-[0.8125rem] font-semibold"
                >
                    <span class="menu-burger" aria-hidden="true"><span></span><span></span></span>
                    <span>Menu</span>
                </button>
            </div>
        </div>
    </header>

    {{-- Fenêtre « Étudiant » : choix entre les services en ligne (Registrar, Moodle…) --}}
    @if ($topbarCta && count($topbarCta['children']))
        <dialog data-student-dialog class="student-dialog" aria-labelledby="student-dialog-title">
            <div class="ornament relative bg-white p-8 text-ink-800 shadow-lift sm:p-10">
                <button type="button" data-student-close class="absolute top-4 right-4 z-20 inline-flex size-10 items-center justify-center text-navy-900 hover:bg-sun-400" aria-label="Fermer">
                    <x-ico name="close" class="size-5" />
                </button>
                <p class="eyebrow mb-3">Espace étudiant</p>
                <h2 id="student-dialog-title" class="font-serif text-2xl text-navy-900 sm:text-3xl">Où souhaitez-vous aller ?</h2>
                <span class="mt-4 mb-7 block h-0.5 w-12 bg-sun-400" aria-hidden="true"></span>
                <ul class="grid gap-4 sm:grid-cols-2">
                    @foreach ($topbarCta['children'] as $child)
                        @php([$name, $hint] = array_pad(explode(' — ', $child['label'], 2), 2, null))
                        <li>
                            <a href="{{ $child['url'] }}" class="group flex h-full flex-col border border-navy-800/15 bg-paper-100 p-6 transition hover:-translate-y-0.5 hover:border-sun-400 hover:bg-navy-900 hover:text-white hover:shadow-lift focus-visible:border-sun-400" @if ($child['new_tab']) target="_blank" rel="noopener noreferrer" @endif>
                                <span class="font-serif text-xl text-navy-900 transition-colors group-hover:text-white">{{ $name }}</span>
                                @if ($hint)<span class="mt-1 text-sm text-ink-600 transition-colors group-hover:text-navy-100">{{ $hint }}</span>@endif
                                <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-royal-600 transition-colors group-hover:text-sun-400">Ouvrir <x-ico name="arrow-up-right" class="size-4" /><span class="sr-only"> (nouvel onglet)</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </dialog>
    @endif

    <x-site.newsletter-dialog />

    {{-- Fenêtre de recherche : s'ouvre avec la loupe, Ctrl/Cmd + K ou « / » ; résultats en direct (components/search-modal.js) --}}
    <div
        x-data="searchModal"
        data-endpoint="{{ route('search.preview') }}"
        @keydown.escape.window="close"
        x-bind:data-open="open"
        data-open="false"
        class="search-modal fixed inset-0 z-[70]"
    >
        <div class="search-modal__backdrop absolute inset-0 bg-night-900/60 backdrop-blur-sm" @click="close" aria-hidden="true"></div>

        <div
            x-ref="panel"
            x-trap.noscroll="open"
            @keydown="onKey"
            role="dialog"
            aria-modal="true"
            aria-label="Rechercher sur le site"
            class="search-modal__panel relative mx-auto mt-[8vh] w-[calc(100%-2rem)] max-w-2xl overflow-hidden rounded-2xl bg-white text-ink-800 shadow-lift sm:mt-[12vh]"
        >
            <form method="get" action="{{ url('/recherche') }}" role="search" class="flex items-center gap-3 border-b border-paper-300 px-5">
                <x-ico name="search" class="size-5 text-royal-600" />
                <label for="search-modal-q" class="sr-only">Rechercher sur le site</label>
                <input
                    id="search-modal-q"
                    x-ref="input"
                    @input="onInput"
                    type="search"
                    name="q"
                    autocomplete="off"
                    placeholder="Formation, actualité, événement…"
                    class="h-16 min-w-0 flex-1 bg-transparent [&::-webkit-search-cancel-button]:hidden text-base text-ink-900 placeholder:text-ink-500 focus:outline-none"
                >
                <span class="search-modal__spinner size-4 shrink-0 rounded-full border-2 border-royal-600 border-t-transparent" x-bind:data-loading="loading" aria-hidden="true"></span>
                <button type="button" @click="close" class="rounded-md border border-paper-300 px-2 py-1 text-[0.6875rem] font-semibold text-ink-600 hover:bg-mist-100">
                    <span aria-hidden="true">Échap</span><span class="sr-only">Fermer la recherche</span>
                </button>
            </form>

            <div class="max-h-[58vh] overflow-y-auto overscroll-contain pb-2" x-bind:aria-busy="loading">
                {{-- Résultats insérés par JavaScript --}}
                <div x-ref="results" aria-live="polite"></div>

                {{-- Accès rapides tant qu'aucune recherche n'est saisie --}}
                <div x-show="empty" class="px-6 py-6">
                    <p class="eyebrow mb-3 font-sans! text-[0.6875rem]! text-ink-600">Accès rapide</p>
                    <ul class="flex flex-wrap gap-2">
                        @foreach (collect($headerMenu)->reject(fn ($i) => $i['url'] === url('/recherche'))->merge($footerMenu)->unique('url')->take(10) as $item)
                            <li><a href="{{ $item['url'] }}" data-search-quick class="inline-flex rounded-none bg-mist-100 px-3.5 py-1.5 text-sm font-medium text-navy-900 hover:bg-royal-600 hover:text-white">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <p class="hidden items-center gap-4 border-t border-paper-300 bg-mist-100 px-5 py-2.5 text-[0.6875rem] text-ink-600 sm:flex">
                <span><kbd class="font-sans font-semibold">↑ ↓</kbd> naviguer</span>
                <span><kbd class="font-sans font-semibold">Entrée</kbd> tous les résultats</span>
                <span><kbd class="font-sans font-semibold">Échap</kbd> fermer</span>
            </p>
        </div>
    </div>

    {{-- Menu plein écran : rideau or puis panneau marine ouverts en cercle depuis le bouton, liens en cascade.
         Animation GSAP (animations/menu.js) ; sans GSAP ou en mouvement réduit, repli CSS (voir app.css). --}}
    <div data-menu-curtain class="menu-curtain fixed inset-0 z-[59] bg-sun-400" aria-hidden="true"></div>
    <div
        id="site-menu"
        x-bind:data-open="open"
        x-trap.noscroll="open"
        role="dialog"
        aria-modal="true"
        aria-label="Menu du site"
        data-open="false"
        class="menu-overlay on-dark fixed inset-0 z-[60] overflow-y-auto bg-navy-900 text-white"
    >
        <x-madagascar class="pointer-events-none absolute top-[8%] right-[-4rem] h-[120%] text-navy-800/70 sm:right-[8%]" data-menu-deco />
        <x-triangle class="pointer-events-none absolute bottom-[-22%] left-[-10%] w-[56rem] text-gold-300/10" data-menu-deco />

        <div class="container-site relative flex min-h-full flex-col pt-5 pb-10">
            <div class="flex h-[calc(var(--header-h)-1.25rem)] items-center justify-between">
                <span class="flex items-center gap-3">
                    <x-seal :size="44" class="size-11" />
                    <span class="font-serif text-xl tracking-[0.04em] uppercase">Zurcher</span>
                </span>
                <button type="button" x-ref="closer" @click="close" class="inline-flex h-11 items-center gap-2.5 rounded-none border border-white/40 px-4 text-sm font-semibold hover:bg-white hover:text-navy-900">
                    <x-ico name="close" class="size-4" /> Fermer <span class="sr-only">le menu</span>
                </button>
            </div>

            <div class="mt-8 grid flex-1 gap-12 lg:mt-12 lg:grid-cols-[1.25fr_1fr_0.85fr] lg:gap-14">
                <nav aria-label="Menu du site">
                    <p class="eyebrow mb-4 menu-overlay__item" data-menu-item>Découvrir</p>
                    <ul class="space-y-1">
                        @foreach ($headerMenu as $item)
                            <li class="menu-overlay__item" data-menu-item>
                                @if (count($item['children']))
                                    <p class="flex items-baseline gap-4 py-1 font-serif text-[clamp(1.5rem,1rem+2.2vw,2.75rem)] leading-tight text-white">
                                        <span class="font-sans text-xs font-semibold tracking-[0.2em] text-gold-300" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span>{{ $item['label'] }}</span>
                                    </p>
                                @else
                                    <a href="{{ $item['url'] }}" class="group flex items-baseline gap-4 py-1 font-serif text-[clamp(1.5rem,1rem+2.2vw,2.75rem)] leading-tight text-white hover:text-gold-300"
                                       @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif>
                                        <span class="font-sans text-xs font-semibold tracking-[0.2em] text-gold-300" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="transition-transform duration-300 group-hover:translate-x-2">{{ $item['label'] }}</span>
                                    </a>
                                @endif
                                @if (count($item['children']))
                                    <ul class="mt-1 mb-3 ml-10 flex flex-wrap gap-x-6 gap-y-1">
                                        @foreach ($item['children'] as $child)
                                            <li><a href="{{ $child['url'] }}" class="text-base text-navy-100 underline-offset-4 hover:text-gold-300 hover:underline">{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                @if (count($footerMenu) || $spaces->isNotEmpty())
                    <nav aria-label="Ressources et autres pages">
                        @if (count($footerMenu))
                            <p class="eyebrow mb-4 menu-overlay__item" data-menu-item>Ressources et pages utiles</p>
                            <ul class="space-y-1">
                                @foreach ($footerMenu as $item)
                                    <li class="menu-overlay__item" data-menu-item>
                                        <a href="{{ $item['url'] }}" class="group flex items-center gap-3 py-1.5 font-serif text-[clamp(1.125rem,1rem+0.7vw,1.5rem)] text-white hover:text-gold-300"
                                           @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif>
                                            <span class="transition-transform duration-300 group-hover:translate-x-2">{{ $item['label'] }}</span>
                                            <x-ico name="arrow-up-right" class="size-4 text-gold-300 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($spaces->isNotEmpty())
                            <p class="eyebrow mt-10 mb-4 menu-overlay__item" data-menu-item>Espaces</p>
                            <ul class="menu-overlay__item flex flex-wrap gap-x-6 gap-y-2 text-sm text-navy-100" data-menu-item>
                                @foreach ($spaces as $item)
                                    <li>
                                        @if ($item['url'] === '#newsletter')
                                            <button type="button" data-newsletter-open aria-haspopup="dialog" class="underline-offset-4 hover:text-white hover:underline">{{ $item['label'] }}</button>
                                        @else
                                            <a href="{{ $item['url'] }}" class="underline-offset-4 hover:text-white hover:underline">{{ $item['label'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </nav>
                @endif

                <div class="menu-overlay__item space-y-8 self-end" data-menu-item>
                    <x-button :href="config('uaz.registration_url')" target="_blank" rel="noopener noreferrer" variant="sun" arrow class="btn-square">S'inscrire</x-button>
                    <address class="space-y-3 text-sm not-italic text-navy-100">
                        @if ($site->address)<p class="flex gap-3"><x-ico name="map-pin" class="mt-0.5 size-4 text-gold-300" />{{ $site->address }}</p>@endif
                        @if ($site->email)<p class="flex gap-3"><x-ico name="mail" class="mt-0.5 size-4 text-gold-300" /><a href="mailto:{{ $site->email }}" class="hover:text-white">{{ $site->email }}</a></p>@endif
                        @if ($site->phone)<p class="flex gap-3"><x-ico name="phone" class="mt-0.5 size-4 text-gold-300" /><a href="tel:{{ preg_replace('/[^+\d]/', '', $site->phone) }}" class="hover:text-white">{{ $site->phone }}</a></p>@endif
                    </address>
                    <ul class="flex gap-3">
                        @foreach ($socials as $key => $label)
                            @if ($site->social($key))
                                <li><a href="{{ $site->social($key) }}" target="_blank" rel="noopener noreferrer" class="inline-flex size-11 items-center justify-center rounded-full border border-white/30 hover:bg-white hover:text-navy-900" aria-label="{{ $label }} (nouvel onglet)"><x-ico :name="$key" class="size-5" /></a></li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
