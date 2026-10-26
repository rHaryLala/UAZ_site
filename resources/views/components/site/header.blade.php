@props(['tone' => 'dark'])

@php
    $over = $tone === 'dark'; // transparent sur héros sombre tant que la page n'est pas défilée
    $socials = [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube',
    ];
@endphp

<div x-data="siteMenu" @keydown.escape.window="close" class="contents">
    <header
        x-bind:data-scrolled="scrolled"
        data-over="{{ $over ? 'true' : 'false' }}"
        class="site-header fixed inset-x-0 top-0 z-50"
    >
        <div class="container-site flex h-[var(--header-h)] items-center justify-between gap-6">
            <a href="{{ url('/') }}" class="site-header__brand flex items-center gap-3" aria-label="{{ $site->site_name }} — accueil">
                <x-seal :size="48" eager class="size-11 md:size-12" />
                <span class="leading-none">
                    <span class="block text-[0.6rem] font-semibold tracking-[0.22em] uppercase opacity-80">Université Adventiste</span>
                    <span class="mt-1 block font-serif text-2xl tracking-[0.08em]">ZURCHER</span>
                </span>
            </a>

            {{-- Navigation principale (grands écrans) --}}
            <nav aria-label="Navigation principale" class="hidden xl:block">
                <ul class="flex items-center gap-1">
                    @foreach ($headerMenu as $item)
                        <li class="group relative">
                            <a href="{{ $item['url'] }}" class="site-header__link inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap"
                               @if ($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                               @if (url()->current() === $item['url']) aria-current="page" @endif>
                                {{ $item['label'] }}
                                @if (count($item['children']))<x-ico name="chevron-down" class="size-3.5 opacity-70 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" />@endif
                            </a>
                            @if (count($item['children']))
                                <ul class="invisible absolute top-full left-0 z-10 min-w-56 translate-y-2 rounded-xl bg-paper-50 p-2 opacity-0 shadow-lift transition duration-200 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" class="block rounded-lg px-4 py-2.5 text-sm font-medium text-ink-800 hover:bg-paper-200 hover:text-navy-800">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                <a href="{{ url('/recherche') }}" class="site-header__icon inline-flex size-11 items-center justify-center rounded-full" aria-label="Rechercher sur le site">
                    <x-ico name="search" class="size-5" />
                </a>
                <x-button :href="url('/admissions')" variant="gold" size="sm" class="hidden md:inline-flex">Candidater</x-button>
                <button
                    type="button"
                    x-ref="trigger"
                    @click="toggle"
                    x-bind:aria-expanded="open"
                    aria-controls="site-menu"
                    class="site-header__icon inline-flex h-11 items-center gap-2.5 rounded-full px-4 text-sm font-semibold"
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
                                <a href="{{ $item['url'] }}" class="group flex items-baseline gap-4 py-1.5 font-serif text-[clamp(2rem,1.2rem+3.4vw,4rem)] leading-tight text-white hover:text-gold-300"
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
                    <x-button :href="url('/admissions')" variant="gold" arrow>Candidater maintenant</x-button>
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
