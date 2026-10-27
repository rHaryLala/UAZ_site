<x-layouts.app title="Notre équipe" description="L'équipe de l'Université Adventiste Zurcher : celles et ceux qui font vivre l'UAZ au quotidien.">
    <x-page-hero eyebrow="Communauté" title="Notre équipe" intro="Celles et ceux qui font vivre l'UAZ au quotidien." :breadcrumbs="[['label' => 'Notre équipe']]" />

    <section class="relative isolate overflow-x-clip bg-paper-100 pt-16 pb-24 md:pt-24 md:pb-32" aria-label="Membres de l'équipe">
        <x-madagascar class="pointer-events-none absolute top-10 -right-24 -z-10 h-[38rem] text-navy-800/[0.04]" />

        <div class="container-site">
            @if ($members->isEmpty())
                <p class="rounded-card bg-paper-50 p-8 text-ink-700 shadow-card">La liste de l'équipe sera bientôt publiée.</p>
            @else
                <div class="mb-10 flex items-end justify-between gap-6 border-b border-navy-800/15 pb-5">
                    <p class="font-serif text-2xl text-navy-800 md:text-3xl">{{ $members->count() }} <span class="text-ink-600">personnes à votre service</span></p>
                    <span class="hidden h-1 w-24 bg-sun-400 sm:block" aria-hidden="true"></span>
                </div>

                <h2 class="sr-only">Liste des membres</h2>
                <ul class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-team-grid>
                    @foreach ($members as $member)
                        <li data-reveal data-reveal-delay="{{ ($loop->index % 4) * 0.06 }}">
                            {{-- Le décalage est sur un conteneur interne : l'apparition (data-reveal) anime le <li>, les deux ne se mélangent pas. --}}
                            <div @class(['xl:translate-y-10' => $loop->index % 4 === 1 || $loop->index % 4 === 3])>
                                <x-person-card :member="$member" :profile="true" />
                            </div>
                            <template data-profile-template>@include('team._profile', ['member' => $member])</template>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Fiche : <dialog> natif (focus piégé, Échap). La carte cliquée se retourne en 3D (GSAP, resources/js/team-profile.js) :
         face avant = copie de la carte, face arrière = la fiche. --}}
    <dialog id="team-profile" class="team-dialog" aria-label="Profil">
        <div class="team-flip" data-flip>
            <div class="team-flip__front" data-flip-front aria-hidden="true"></div>
            <div class="team-flip__back ornament" data-flip-back>
                <div class="team-flip__inner" data-flip-inner>
                    <button type="button" data-profile-close class="absolute top-4 right-4 z-20 inline-flex size-11 items-center justify-center bg-white text-navy-900 shadow-card hover:bg-sun-400" aria-label="Fermer le profil">
                        <x-ico name="close" class="size-5" />
                    </button>
                    <div data-profile-body></div>
                </div>
            </div>
        </div>
    </dialog>
</x-layouts.app>
