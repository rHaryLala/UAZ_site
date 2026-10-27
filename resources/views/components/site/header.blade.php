@php
    $socials = [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube',
    ];
    // Le dernier lien de la barre du haut est mis en avant (« Se connecter » dans la maquette).
    $topbarLinks = collect($topbarMenu);
    $topbarCta = $topbarLinks->count() > 1 ? $topbarLinks->last() : null;
    $topbarPlain = $topbarCta ? $topbarLinks->slice(0, -1) : $topbarLinks;
@endphp

<div x-data="siteMenu" @keydown.escape.window="close" class="contents">
    {{-- Barre du haut : devise et liens secondaires (menu « topbar » éditable) --}}
    <div class="topbar text-[0.6875rem]" role="region" aria-label="Devise et liens secondaires">
        <div class="container-site flex min-h-9 items-center justify-between gap-6">
            <p class="tracking-[0.01em] text-sun-400 uppercase">Spiritus · Manus · Mens — Fondée en {{ config("uaz.founded_year") }}.</p>
            <nav aria-label="Liens secondaires" class="hidden items-center gap-6 md:flex">
                @foreach ($topbarPlain as $item)
                    <a href="{{ $item['url'] }}" class="py-2" @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                @endforeach
                @if ($topbarCta)
                    <a href="{{ $topbarCta['url'] }}" class="rounded-full bg-sun-400 px-4 py-1.5 font-semibold text-night-900 hover:text-night-900! hover:bg-sun-300" @if ($topbarCta['new_tab']) target="_blank" rel="noopener noreferrer" @endif>{{ $topbarCta['label'] }}</a>
                @endif
            </nav>
        </div>
    </div>

    <header x-bind:data-scrolled="scrolled" x-bind:data-dark="dark" class="site-header sticky top-0 z-50">
        <div class="container-site flex h-[var(--header-h)] items-center justify-between gap-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3.5" aria-label="{{ $site->site_name }} — accueil">
                <x-seal :size="56" eager class="size-11 md:size-14" />
                <span class="leading-none">
                    <span class="brand-tag block text-[0.6rem] font-medium tracking-[0.02em] text-navy-900 uppercase md:text-[0.65rem]">Université Adventiste</span>
                    <span class="brand-name mt-1 block font-serif text-lg font-bold text-royal-600 uppercase md:text-[1.3rem]">Zurcher</span>
                </span>
            </a>

            <div class="flex items-center gap-3 sm:gap-7">
                {{-- Navigation principale (grands écrans) --}}
            <nav aria-label="Navigation principale" class="hidden xl:block">
                    <ul class="flex items-center gap-7">
                        @foreach ($headerMenu as $item)
                            <li class="group relative">
                                <a href="{{ $item['url'] }}" class="site-header__link inline-flex items-center gap-1.5 py-2 text-[0.8125rem] font-medium whitespace-nowrap"
                                   @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                   @if (url()->current() === $item['url']) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                </a>
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
                <a href="{{ url('/recherche') }}" class="site-header__icon inline-flex size-11 items-center justify-center" aria-label="Rechercher sur le site">
                    <x-ico name="search" class="size-5" />
                </a>
                <x-button :href="url('/admissions')" variant="royal" arrow class="btn-square hidden md:inline-flex">S'inscrire</x-button>
                <button
                    type="button"
                    x-ref="trigger"
                    @click="toggle"
                    x-bind:aria-expanded="open"
                    aria-controls="site-menu"
                    class="site-header__icon inline-flex h-11 items-center gap-2.5 px-1 text-[0.8125rem] font-semibold xl:hidden"
                >
                    <span class="menu-burger" aria-hidden="true"><span></span><span></span></span>
                    <span>Menu</span>
                </button>
            </div>
        </div>
    </header>

    {{-- Menu plein écran : ouverture en cercle depuis le bouton, liens en cascade (CSS, voir app.css) --}}
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
        <x-madagascar class="pointer-events-none absolute top-[8%] right-[-4rem] h-[120%] text-navy-800/70 sm:right-[8%]" />
        <x-triangle class="pointer-events-none absolute bottom-[-22%] left-[-10%] w-[56rem] text-gold-300/10" />

        <div class="container-site relative flex min-h-full flex-col pt-5 pb-10">
            <div class="flex h-[calc(var(--header-h)-1.25rem)] items-center justify-between">
                <span class="flex items-center gap-3">
                    <x-seal :size="44" class="size-11" />
                    <span class="font-serif text-xl tracking-[0.08em]">ZURCHER</span>
                </span>
                <button type="button" @click="close" class="inline-flex h-11 items-center gap-2.5 rounded-full border border-white/40 px-4 text-sm font-semibold hover:bg-white hover:text-navy-900">
                    <x-ico name="close" class="size-4" /> Fermer <span class="sr-only">le menu</span>
                </button>
            </div>

            <div class="mt-10 grid flex-1 gap-12 lg:mt-16 lg:grid-cols-[1.4fr_1fr] lg:gap-20">
                <nav aria-label="Menu du site">
                    <ul class="space-y-1">
                        @foreach ($headerMenu as $item)
                            <li class="menu-overlay__item" style="--i: {{ $loop->index }}">
                                <a href="{{ $item['url'] }}" class="group flex items-baseline gap-4 py-1.5 font-serif text-[clamp(1.75rem,1.1rem+2.8vw,3.25rem)] leading-tight text-white hover:text-gold-300"
                                   @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif>
                                    <span class="font-sans text-xs font-semibold tracking-[0.2em] text-gold-300" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="transition-transform duration-300 group-hover:translate-x-2">{{ $item['label'] }}</span>
                                </a>
                                @if (count($item['children']))
                                    <ul class="mt-1 mb-3 ml-12 flex flex-wrap gap-x-6 gap-y-1">
                                        @foreach ($item['children'] as $child)
                                            <li><a href="{{ $child['url'] }}" class="text-sm text-navy-100 underline-offset-4 hover:text-white hover:underline">{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="menu-overlay__item space-y-8 self-end" style="--i: {{ count($headerMenu) + 1 }}">
                    <x-button :href="url('/admissions')" variant="gold" arrow>S'inscrire</x-button>
                    @if ($topbarLinks->isNotEmpty())
                        <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-navy-100">
                            @foreach ($topbarLinks as $item)
                                <li><a href="{{ $item['url'] }}" class="underline-offset-4 hover:text-white hover:underline">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
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
