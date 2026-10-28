<x-layouts.app :title="$title" :noindex="true">
    <x-page-hero :title="$title" />

    <x-section tone="paper">
        <div class="mx-auto max-w-2xl">
            <p class="text-lg text-ink-700">{{ $text }}</p>
            <a href="{{ url('/') }}" class="link-arrow mt-8 w-fit">Retour à l'accueil</a>
        </div>
    </x-section>
</x-layouts.app>
