@php
    $pill = fn (bool $on) => 'inline-flex min-h-11 items-center rounded-full border px-5 text-sm font-semibold transition '.($on
        ? 'border-navy-800 bg-navy-800 text-white'
        : 'border-ink-300 bg-paper-50 text-ink-800 hover:border-navy-800');
    $qs = fn (array $extra) => url('/formations').(count($q = array_filter(array_merge(['niveau' => $level?->value, 'faculte' => $faculty?->slug], $extra))) ? '?'.http_build_query($q) : '');
@endphp

<x-layouts.app title="Formations" description="Licences, masters et formations de l'Université Adventiste Zurcher : mentions, parcours, durée et conditions d'admission.">
    <x-page-hero :image="\App\Models\SiteSetting::current()->formationsHeroImage" eyebrow="Formations" title="Une formation. Un avenir. Une vision." intro="Découvrez les mentions de l'UAZ et trouvez le parcours adapté à vos objectifs." :breadcrumbs="[['label' => 'Formations']]" />

    <x-section tone="paper" tight>
      <div data-ajax-region>
        <form method="get" action="{{ url('/formations') }}" aria-label="Filtrer les formations" class="mb-12 space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="mr-2 text-sm font-semibold text-ink-700">Niveau</span>
                <a href="{{ $qs(['niveau' => null]) }}" class="{{ $pill(! $level) }}" @if (! $level) aria-current="true" @endif>Tous</a>
                @foreach ($levels as $item)
                    <a href="{{ $qs(['niveau' => $item->value]) }}" class="{{ $pill($level === $item) }}" @if ($level === $item) aria-current="true" @endif>{{ $item->getLabel() }}</a>
                @endforeach
            </div>
            @if ($faculties->count() > 1)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="mr-2 text-sm font-semibold text-ink-700">Domaine</span>
                    <a href="{{ $qs(['faculte' => null]) }}" class="{{ $pill(! $faculty) }}" @if (! $faculty) aria-current="true" @endif>Tous</a>
                    @foreach ($faculties as $item)
                        <a href="{{ $qs(['faculte' => $item->slug]) }}" class="{{ $pill((bool) $faculty?->is($item)) }}" @if ($faculty?->is($item)) aria-current="true" @endif>{{ $item->name }}</a>
                    @endforeach
                </div>
            @endif
        </form>

        <p class="mb-6 text-sm text-ink-700" role="status">{{ trans_choice('{0} Aucune formation|{1} :count formation|[2,*] :count formations', $formations->count()) }}</p>

        @if ($formations->isEmpty())
            <p class="rounded-card bg-paper-50 p-8 text-ink-700 shadow-card">Aucune formation ne correspond à ces critères. <a href="{{ url('/formations') }}" class="link-arrow">Voir toutes les formations</a></p>
        @else
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($formations as $formation)
                    <li data-reveal data-reveal-delay="{{ ($loop->index % 3) * 0.08 }}"><x-formation-card :formation="$formation" level="h2" :eager="$loop->first" class="h-full" /></li>
                @endforeach
            </ul>
        @endif
      </div>
    </x-section>

    <x-cta-banner title="Une question sur une formation ?" text="Le bureau académique vous accompagne dans votre choix et votre candidature." :primary="['label' => 'Nous contacter', 'url' => url('/contact')]" :secondary="['label' => 'Conditions d\'admission', 'url' => url('/admissions/licence')]" />
</x-layouts.app>
