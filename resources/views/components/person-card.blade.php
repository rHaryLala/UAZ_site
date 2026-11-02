@props(['member', 'eager' => false, 'profile' => false])

{{-- Carte portrait : photo plein cadre, nom et fonction sur un dégradé marine, liseré or et e-mail qui se révèlent au survol. --}}
<article {{ $attributes->class('ornament group relative isolate overflow-hidden bg-navy-900 text-white shadow-card transition-shadow duration-300 hover:shadow-lift focus-within:shadow-lift') }}>
    <div class="relative aspect-[4/5] overflow-hidden">
        @if ($member->photo)
            <x-picture :asset="$member->photo" conversion="card" sizes="(min-width: 1280px) 20rem, (min-width: 640px) 30vw, 92vw" :eager="$eager"
                class="size-full object-cover transition-transform duration-[400ms] ease-out group-hover:scale-105" />
        @else
            <x-madagascar class="absolute inset-y-6 right-[-12%] h-[85%] text-navy-700/70 transition-transform duration-[400ms] group-hover:scale-105" />
            <span class="absolute top-5 left-6 font-serif text-6xl text-sun-400" aria-hidden="true">
                {{ collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </span>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-night-900 via-night-900/55 to-transparent" aria-hidden="true"></div>
    </div>

    @if ($profile)
        {{-- Bouton étiré sur toute la carte : ouvre la fiche agrandie (resources/js/team-profile.js) ; le lien e-mail reste au-dessus. --}}
        <button type="button" data-profile-open aria-haspopup="dialog" class="absolute inset-0 z-[1] cursor-pointer">
            <span class="sr-only">{{ __('Voir le profil de :name', ['name' => $member->name]) }}</span>
        </button>
    @endif

    <div @class(['absolute inset-x-0 bottom-0 p-5', 'pointer-events-none' => $profile])>
        <span class="mb-3 block h-0.5 w-8 bg-sun-400 transition-all duration-300 group-hover:w-16" aria-hidden="true"></span>
        <h3 class="font-serif text-lg leading-snug text-white! md:text-xl">{{ $member->name }}</h3>
        @if ($member->job_title)
            <p class="mt-1 text-sm font-medium text-sun-400">{{ $member->job_title }}</p>
        @endif
        @if ($member->faculty)
            <p class="text-xs text-white/70">{{ $member->faculty->name }}</p>
        @endif
        @if ($member->email)
            <p class="grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 group-focus-within:grid-rows-[1fr] group-hover:grid-rows-[1fr] max-lg:grid-rows-[1fr]">
                <span class="overflow-hidden">
                    <a href="mailto:{{ $member->email }}" class="pointer-events-auto relative z-[2] mt-3 inline-flex items-center gap-2 text-xs break-all text-white/90 underline decoration-sun-400 decoration-2 underline-offset-4 hover:text-white">
                        <x-ico name="mail" class="size-4 text-sun-400" />{{ $member->email }}
                    </a>
                </span>
            </p>
        @endif
    </div>
</article>
