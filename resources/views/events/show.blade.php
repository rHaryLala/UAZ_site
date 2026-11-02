@php($end = $event->ends_at)

<x-layouts.app :title="$event->metaTitle()" :description="$event->metaDescription()" :image="$event->metaImage()" :noindex="! $event->isLive()">
    <x-page-hero :eyebrow="__('Événement')" :title="$event->title" :intro="$event->summary" :image="$event->cover"
        :breadcrumbs="[['label' => 'Événements', 'url' => url('/evenements')], ['label' => str($event->title)->limit(48)->toString()]]" />

    <x-section tone="paper">
        <div class="grid gap-14 lg:grid-cols-[1fr_20rem] lg:gap-20">
            <div><x-rich :html="$event->description" /></div>

            <aside class="lg:sticky lg:top-28 lg:self-start" aria-label="{{ __('Informations pratiques') }}">
                <div class="card p-7">
                    <h2 class="eyebrow mb-5">{{ __('Informations pratiques') }}</h2>
                    <dl class="space-y-5 text-sm">
                        <div class="flex gap-3"><x-ico name="calendar" class="mt-0.5 size-5 text-gold-600" />
                            <div><dt class="sr-only">{{ __('Date') }}</dt><dd class="font-semibold text-ink-900">
                                <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('l j F Y') }}</time>
                                @if ($end && ! $end->isSameDay($event->starts_at)) <span class="font-normal text-ink-700"> {{ __('au') }} </span><time datetime="{{ $end->toIso8601String() }}">{{ $end->translatedFormat('j F Y') }}</time>@endif
                            </dd></div></div>
                        <div class="flex gap-3"><x-ico name="clock" class="mt-0.5 size-5 text-gold-600" />
                            <div><dt class="sr-only">{{ __('Horaire') }}</dt><dd class="font-semibold text-ink-900">@if ($event->is_all_day) {{ __('Toute la journée') }} @else {{ \App\Support\Locales::time($event->starts_at) }}@if ($end) – {{ \App\Support\Locales::time($end) }}@endif @endif</dd></div></div>
                        @if ($event->location)
                            <div class="flex gap-3"><x-ico name="map-pin" class="mt-0.5 size-5 text-gold-600" />
                                <div><dt class="sr-only">{{ __('Lieu') }}</dt><dd class="font-semibold text-ink-900">{{ $event->location }}
                                    @if ($event->location_url)<a href="{{ $event->location_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block text-sm font-medium text-navy-800 underline decoration-gold-500 decoration-2 underline-offset-4">{{ __('Voir le plan (nouvel onglet)') }}</a>@endif</dd></div></div>
                        @endif
                    </dl>
                    @if ($event->registration_url)
                        <x-button :href="$event->registration_url" variant="navy" arrow class="mt-7 w-full" target="_blank" rel="noopener noreferrer">{{ __('S\'inscrire') }}</x-button>
                    @endif
                </div>
            </aside>
        </div>
        <p class="mt-14 border-t border-paper-300 pt-8"><a href="{{ url('/evenements') }}" class="link-arrow"><x-ico name="arrow-left" class="size-4" /> {{ __('Tous les événements') }}</a></p>
    </x-section>

    @push('head')
        @include('partials.jsonld-event', ['event' => $event])
    @endpush
</x-layouts.app>
