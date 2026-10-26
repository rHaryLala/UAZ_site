@php
    $facts = array_filter([
        'Niveau' => $formation->level->getLabel(),
        'Mention' => $formation->mention,
        'Durée' => $formation->duration,
        'Crédits' => $formation->credits ? $formation->credits.' ECTS' : null,
        $formation->faculty?->type->getLabel() ?? 'Département' => $formation->faculty?->name,
    ]);
@endphp

<x-layouts.app :title="$formation->metaTitle()" :description="$formation->metaDescription()" :image="$formation->metaImage()" :noindex="! $formation->isLive()">
    <x-page-hero :eyebrow="$formation->level->getLabel().($formation->faculty ? ' · '.$formation->faculty->name : '')" :title="$formation->title" :intro="$formation->summary" :image="$formation->cover"
        :breadcrumbs="[['label' => 'Formations', 'url' => url('/formations')], ['label' => $formation->title]]" />

    <x-section tone="paper">
        <div class="grid gap-14 lg:grid-cols-[1fr_20rem] lg:gap-20">
            <div class="space-y-14">
                @if (filled($formation->description))
                    <div><h2 class="mb-5 text-h3">Présentation</h2><x-rich :html="$formation->description" /></div>
                @endif
                @if (filled($formation->career_prospects))
                    <div><h2 class="mb-5 text-h3">Débouchés</h2><x-rich :html="$formation->career_prospects" /></div>
                @endif
                @if (filled($formation->admission_requirements))
                    <div><h2 class="mb-5 text-h3">Conditions d'admission</h2><x-rich :html="$formation->admission_requirements" /></div>
                @endif
            </div>

            <aside class="lg:sticky lg:top-28 lg:self-start" aria-label="En bref">
                <div class="card p-7">
                    <h2 class="eyebrow mb-5">En bref</h2>
                    <dl class="space-y-4 text-sm">
                        @foreach ($facts as $label => $value)
                            <div class="border-b border-paper-300 pb-3 last:border-0 last:pb-0"><dt class="text-ink-600">{{ $label }}</dt><dd class="mt-0.5 font-semibold text-ink-900">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    <div class="mt-7 space-y-3">
                        <x-button :href="url('/admissions')" variant="navy" arrow class="w-full">Candidater</x-button>
                        <x-button :href="url('/contact?sujet='.urlencode('Formation : '.$formation->title))" variant="outline" class="w-full">Poser une question</x-button>
                    </div>
                </div>
            </aside>
        </div>
    </x-section>

    @if ($related->isNotEmpty())
        <x-section tone="sand" title="Dans le même domaine">
            <ul class="grid gap-6 md:grid-cols-3">
                @foreach ($related as $item)
                    <li><x-formation-card :formation="$item" class="h-full" /></li>
                @endforeach
            </ul>
        </x-section>
    @endif

    @push('head')
        {{-- Données structurées (Course) : voir resources/views/partials/jsonld-course.blade.php --}}
        @include('partials.jsonld-course', ['formation' => $formation])
    @endpush
</x-layouts.app>
