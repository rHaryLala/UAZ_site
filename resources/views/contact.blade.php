@php
    $hasCoords = filled($site->latitude) && filled($site->longitude);
    $osmEmbed = $hasCoords
        ? 'https://www.openstreetmap.org/export/embed.html?bbox='.($site->longitude - 0.01).'%2C'.($site->latitude - 0.006).'%2C'.($site->longitude + 0.01).'%2C'.($site->latitude + 0.006).'&layer=mapnik&marker='.$site->latitude.'%2C'.$site->longitude
        : null;
    $osmLink = $hasCoords
        ? 'https://www.openstreetmap.org/?mlat='.$site->latitude.'&mlon='.$site->longitude.'#map=15/'.$site->latitude.'/'.$site->longitude
        : 'https://www.openstreetmap.org/search?query='.urlencode((string) $site->address);
@endphp

<x-layouts.app title="Contact" description="Contactez l'Université Adventiste Zurcher : admissions, formations, vie étudiante.">
    <x-page-hero eyebrow="Contact" title="Contactez-nous" intro="Une question sur nos programmes, nos admissions ou la vie étudiante ? Notre équipe est à votre écoute pour vous accompagner." :breadcrumbs="[['label' => 'Contact']]" />

    <x-section tone="paper">
        <div class="grid gap-14 lg:grid-cols-[1.2fr_1fr] lg:gap-20">
            <div>
                <h2 class="mb-8 text-h3">Écrivez-nous</h2>
                <livewire:contact-form />
            </div>

            <div class="space-y-10">
                <div>
                    <h2 class="mb-6 text-h3">Nos coordonnées</h2>
                    <address class="space-y-4 text-ink-800 not-italic">
                        @if ($site->address)<p class="flex gap-4"><x-ico name="map-pin" class="mt-1 size-5 text-gold-600" />{{ $site->address }}</p>@endif
                        @if ($site->email)<p class="flex gap-4"><x-ico name="mail" class="mt-1 size-5 text-gold-600" /><a href="mailto:{{ $site->email }}" class="underline decoration-gold-500 decoration-2 underline-offset-4 hover:text-navy-800">{{ $site->email }}</a></p>@endif
                        @if ($site->phone)<p class="flex gap-4"><x-ico name="phone" class="mt-1 size-5 text-gold-600" /><a href="tel:{{ preg_replace('/[^+\d]/', '', $site->phone) }}" class="underline decoration-gold-500 decoration-2 underline-offset-4 hover:text-navy-800">{{ $site->phone }}</a></p>@endif
                        @if (! empty($site->opening_hours))
                            <div class="flex gap-4"><x-ico name="clock" class="mt-1 size-5 text-gold-600" />
                                <dl>@foreach ($site->opening_hours as $line)<div class="mb-1"><dt class="inline font-semibold">{{ $line['days'] }} :</dt> <dd class="inline">{{ $line['hours'] }}</dd></div>@endforeach</dl></div>
                        @endif
                    </address>
                </div>

                {{-- Carte : chargée seulement au clic (vie privée et performance : aucun appel externe au chargement) --}}
                <div class="overflow-hidden rounded-card bg-navy-800 text-white shadow-card" data-map>
                    @if ($osmEmbed)
                        <details class="group">
                            <summary class="flex cursor-pointer items-center justify-between gap-4 p-6 font-semibold marker:hidden [&::-webkit-details-marker]:hidden">
                                <span class="inline-flex items-center gap-3"><x-ico name="map-pin" class="size-5 text-gold-300" /> Afficher la carte</span>
                                <x-ico name="chevron-down" class="size-5 transition-transform group-open:rotate-180" />
                            </summary>
                            <iframe src="{{ $osmEmbed }}" title="Carte : emplacement du campus de l'UAZ" loading="lazy" referrerpolicy="no-referrer" class="aspect-[4/3] w-full border-0"></iframe>
                        </details>
                    @else
                        <div class="relative p-6">
                            <x-madagascar class="pointer-events-none absolute inset-y-3 right-4 h-[90%] text-navy-700" />
                            <p class="relative text-sm text-navy-100">{{ $site->address ?: 'Antsirabe, Madagascar' }}</p>
                        </div>
                    @endif
                    <a href="{{ $osmLink }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between border-t border-white/15 p-4 text-sm font-semibold text-gold-300 hover:text-white">
                        Ouvrir dans OpenStreetMap <span class="sr-only">(nouvel onglet)</span><x-ico name="arrow-up-right" class="size-4" />
                    </a>
                </div>
            </div>
        </div>
    </x-section>
</x-layouts.app>
