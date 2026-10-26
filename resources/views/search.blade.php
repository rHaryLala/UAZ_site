<x-layouts.app title="Recherche" description="Rechercher une formation, une actualité ou un événement de l'UAZ." noindex>
    <x-page-hero eyebrow="Recherche" title="Que cherchez-vous ?" :breadcrumbs="[['label' => 'Recherche']]" />

    <x-section tone="paper" tight>
        <livewire:search />
    </x-section>
</x-layouts.app>
