@php
    $donation = config('uaz.donation_url');
    $contact = url('/contact?sujet='.rawurlencode('Soutenir le projet : '.$project->title));
@endphp

<x-layouts.app :title="$project->title" :description="$project->summary ?: 'Un projet à soutenir de l\'Université Adventiste Zurcher.'" :image="$project->cover ? $project->cover->url('hero') : null">
    <x-page-hero :image="$project->cover" eyebrow="Projet à soutenir" :title="$project->title" :intro="$project->summary" :breadcrumbs="[['label' => 'Soutenir l\'UAZ', 'url' => url('/soutenir')], ['label' => $project->title]]" />

    <section class="bg-paper-100 py-16 md:py-24">
        <div class="container-site grid gap-12 lg:grid-cols-[1fr_22rem] lg:gap-20">
            <div>
                @if ($project->body)
                    <x-rich :html="$project->body" />
                @else
                    <p class="text-lead text-ink-700">{{ $project->summary }}</p>
                @endif
            </div>

            <aside class="space-y-6 lg:sticky lg:top-32 lg:self-start">
                <div class="ornament relative border border-navy-800/10 bg-white p-8 shadow-card">
                    <dl class="space-y-5">
                        @if ($project->status_label)<div><dt class="eyebrow mb-1 font-sans! text-[0.6875rem]!">Avancement</dt><dd class="font-serif text-lg text-navy-900">{{ $project->status_label }}</dd></div>@endif
                        @if ($project->location)<div><dt class="eyebrow mb-1 font-sans! text-[0.6875rem]!">Lieu</dt><dd class="font-serif text-lg text-navy-900">{{ $project->location }}</dd></div>@endif
                        @if ($project->budget)<div><dt class="eyebrow mb-1 font-sans! text-[0.6875rem]!">Budget prévu</dt><dd class="font-serif text-lg text-navy-900">{{ $project->budget }}</dd></div>@endif
                    </dl>
                    <div class="mt-8 space-y-3">
                        @if ($donation)
                            <x-button :href="$donation" target="_blank" rel="noopener noreferrer" variant="royal" arrow class="btn-square w-full">Faire un don</x-button>
                        @endif
                        <x-button :href="$contact" variant="{{ $donation ? 'outline' : 'royal' }}" arrow class="btn-square w-full">Soutenir ce projet</x-button>
                        @if ($project->url)
                            <a href="{{ $project->url }}" target="_blank" rel="noopener noreferrer" class="link-arrow">En savoir plus <x-ico name="arrow-up-right" class="size-4" /><span class="sr-only"> (nouvel onglet)</span></a>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if ($others->isNotEmpty())
        <section class="bg-white py-16 md:py-24">
            <div class="container-site">
                <h2 class="mb-10 text-h2">Autres projets</h2>
                <ul class="grid gap-6 md:grid-cols-2">
                    @foreach ($others as $other)
                        <li>
                            <article class="ornament group relative h-full border border-navy-800/10 bg-paper-100 p-8 transition hover:-translate-y-1 hover:shadow-lift">
                                <h3 class="text-h3"><a href="{{ $other->url() }}" class="stretched-link hover:text-royal-600">{{ $other->title }}</a></h3>
                                @if ($other->summary)<p class="mt-3 text-ink-700">{{ $other->summary }}</p>@endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.app>
