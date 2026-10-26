<x-layouts.app title="Notre équipe" description="L'équipe administrative et enseignante de l'Université Adventiste Zurcher.">
    <x-page-hero eyebrow="Communauté" title="Notre équipe" intro="Celles et ceux qui font vivre l'UAZ au quotidien." :breadcrumbs="[['label' => 'Notre équipe']]" />

    @forelse ($groups as $label => $members)
        <x-section :tone="$loop->even ? 'sand' : 'paper'" :title="$label" :tight="true">
            <ul class="grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($members as $member)
                    <li data-reveal data-reveal-delay="{{ ($loop->index % 4) * 0.06 }}"><x-person-card :member="$member" /></li>
                @endforeach
            </ul>
        </x-section>
    @empty
        <x-section tone="paper" tight><p class="rounded-card bg-paper-50 p-8 text-ink-700 shadow-card">La liste de l'équipe sera bientôt publiée.</p></x-section>
    @endforelse
</x-layouts.app>
