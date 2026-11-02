<x-layouts.app :title="__('Événements')" :description="__('Les événements à venir et passés de l\'Université Adventiste Zurcher.')">
    <x-page-hero :eyebrow="__('Agenda')" :title="__('Événements')" :intro="__('Rencontres, cultes, concours, journées portes ouvertes : retrouvez la vie du campus.')" :breadcrumbs="[['label' => 'Événements']]" />

    <x-section tone="paper" tight>
        <h2 class="mb-8 text-h3">{{ __('À venir') }}</h2>
        @if ($upcoming->isEmpty())
            <p class="rounded-card bg-paper-50 p-8 text-ink-700 shadow-card">{{ __('Aucun événement programmé pour le moment. Revenez bientôt ou suivez-nous sur les réseaux sociaux.') }}</p>
        @else
            <ul class="grid gap-5 md:grid-cols-2">
                @foreach ($upcoming as $event)
                    <li data-reveal><x-event-card :event="$event" /></li>
                @endforeach
            </ul>
        @endif
    </x-section>

    @if ($past->isNotEmpty())
        <x-section tone="sand" tight>
            <h2 class="mb-8 text-h3">{{ __('Événements passés') }}</h2>
            <ul class="grid gap-5 md:grid-cols-2">
                @foreach ($past as $event)
                    <li><x-event-card :event="$event" class="opacity-90" /></li>
                @endforeach
            </ul>
            {{ $past->links() }}
        </x-section>
    @endif
</x-layouts.app>
