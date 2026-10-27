@php
    $socials = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];
@endphp

<footer class="on-dark relative isolate overflow-hidden bg-navy-950 text-navy-100">
    <x-madagascar class="pointer-events-none absolute -right-10 bottom-0 -z-10 h-[110%] translate-y-1/4 text-navy-900" />

    <div class="container-site grid gap-12 py-16 md:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1.1fr_1fr] lg:py-20">
        <div>
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3 text-white" aria-label="{{ $site->site_name }} — accueil">
                <x-seal :size="64" class="size-16" />
                <span class="leading-none">
                    {{-- Justifié sur la largeur de « ZURCHER » (w-0 min-w-full : ne l'élargit jamais). --}}
                    <span class="block w-0 min-w-full text-justify [text-align-last:justify] text-[0.88rem] font-medium uppercase opacity-90">Université Adventiste</span>
                    <span class="mt-1 block font-serif text-3xl tracking-[0.03em]">ZURCHER</span>
                </span>
            </a>
            {{-- Adresse au-dessus de la devise ; un clic ouvre la carte Google Maps du campus. --}}
            @if ($site->address)
                <a href="{{ config('uaz.map_url') }}" target="_blank" rel="noopener noreferrer" class="group mt-6 flex max-w-xs gap-3 text-sm text-white/90 hover:text-white">
                    <x-ico name="map-pin" class="mt-0.5 size-4 text-gold-300" />
                    <span class="underline decoration-gold-300/60 decoration-1 underline-offset-4 group-hover:decoration-gold-300">{{ $site->address }}<span class="sr-only"> — voir sur Google Maps (nouvel onglet)</span></span>
                </a>
            @endif
            <ul class="mt-6 flex gap-3">
                @foreach ($socials as $key => $label)
                    @if ($site->social($key))
                        <li><a href="{{ $site->social($key) }}" target="_blank" rel="noopener noreferrer" class="inline-flex size-11 items-center justify-center rounded-full border border-white/25 text-white hover:bg-white hover:text-navy-900" aria-label="{{ $label }} (nouvel onglet)"><x-ico :name="$key" class="size-5" /></a></li>
                    @endif
                @endforeach
            </ul>
        </div>

        <nav aria-labelledby="footer-resources">
            <h2 id="footer-resources" class="eyebrow mb-5 font-sans text-xs! text-gold-300!">Ressources</h2>
            <ul class="space-y-3 text-sm">
                @foreach ($footerMenu as $item)
                    <li><a href="{{ $item['url'] }}" class="text-white/90 underline-offset-4 hover:text-white hover:underline">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div>
            <h2 class="eyebrow mb-5 font-sans text-xs! text-gold-300!">Contact</h2>
            <address class="space-y-3 text-sm not-italic">
                @if ($site->email)<p class="flex gap-3"><x-ico name="mail" class="mt-0.5 size-4 text-gold-300" /><a href="mailto:{{ $site->email }}" class="text-white/90 hover:text-white hover:underline">{{ $site->email }}</a></p>@endif
                @if ($site->phone)<p class="flex gap-3"><x-ico name="phone" class="mt-0.5 size-4 text-gold-300" /><a href="tel:{{ preg_replace('/[^+\d]/', '', $site->phone) }}" class="text-white/90 hover:text-white hover:underline">{{ $site->phone }}</a></p>@endif
            </address>

            {{-- Les horaires ont leur propre rubrique : ce n'est pas un moyen de contact. --}}
            @if (! empty($site->opening_hours))
                <h2 class="eyebrow mt-9 mb-4 font-sans text-xs! text-gold-300!">Horaires</h2>
                <div class="flex gap-3 text-sm">
                    <x-ico name="clock" class="mt-0.5 size-4 text-gold-300" />
                    <dl>
                        @foreach ($site->opening_hours as $line)
                            {{-- Jours sur une ligne, horaires dessous avec un retrait. --}}
                            <div class="mb-3 last:mb-0"><dt class="font-medium text-white">{{ $line['days'] }}</dt><dd class="mt-0.5 pl-5">{{ $line['hours'] }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>

        <nav aria-labelledby="footer-links">
            <h2 id="footer-links" class="eyebrow mb-5 font-sans text-xs! text-gold-300!">Liens utiles</h2>
            <ul class="space-y-3 text-sm">
                @foreach ($headerMenu as $item)
                    <li><a href="{{ $item['url'] }}" class="text-white/90 underline-offset-4 hover:text-white hover:underline">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>

    <div class="border-t border-white/10">
        <div class="container-site flex flex-col gap-2 py-6 text-xs text-navy-200 sm:flex-row sm:items-center sm:justify-between">
            <p>{{ $site->footer_legal ?: '© '.$site->site_name }} — {{ now()->year }}</p>
            <p class="font-serif tracking-[0.2em] uppercase text-gold-300">Spiritus · Manus · Mens</p>
        </div>
    </div>
</footer>
