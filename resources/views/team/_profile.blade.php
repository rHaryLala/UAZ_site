{{-- Contenu de la fiche agrandie (copié dans la fenêtre par resources/js/team-profile.js). Aucune image n'est chargée tant que la fiche n'est pas ouverte. --}}
<div class="grid md:grid-cols-[0.85fr_1.15fr]">
    <div class="relative min-h-72 bg-navy-900 md:min-h-[32rem]">
        @if ($member->photo)
            <x-picture :asset="$member->photo" conversion="portrait" sizes="(min-width: 768px) 24rem, 90vw" class="absolute inset-0 size-full object-cover" />
        @else
            <x-madagascar class="absolute inset-y-6 right-[-12%] h-[85%] text-navy-700/70" />
            <span class="absolute top-6 left-7 font-serif text-7xl text-sun-400" aria-hidden="true">
                {{ collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </span>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-night-900/70 via-transparent to-transparent md:bg-gradient-to-r md:from-transparent md:via-transparent md:to-night-900/10" aria-hidden="true"></div>
    </div>

    <div class="flex flex-col justify-center p-7 sm:p-10 md:p-12" data-profile-content>
        <span class="mb-5 block h-1 w-12 bg-sun-400" aria-hidden="true"></span>
        <h2 class="font-serif text-2xl leading-snug text-navy-900 md:text-3xl" data-profile-name>{{ $member->name }}</h2>
        @if ($member->job_title)
            <p class="mt-2 font-medium text-royal-600">{{ $member->job_title }}</p>
        @endif
        @if ($member->faculty)
            <p class="text-sm text-ink-600">{{ $member->faculty->name }}</p>
        @endif

        @if ($member->quote)
            <blockquote class="relative mt-8 border-l-2 border-sun-400 pl-6 font-serif text-lg leading-relaxed text-navy-800 italic md:text-xl">
                <span class="absolute -top-5 -left-3 font-serif text-6xl leading-none text-sun-400" aria-hidden="true">“</span>
                {{ $member->quote }}
            </blockquote>
        @endif

        @if ($member->bio)
            <div class="mt-6">
                <p class="eyebrow mb-2 font-sans! text-[0.6875rem]!">{{ __('Message aux lecteurs') }}</p>
                <p class="text-ink-700">{!! nl2br(e($member->bio)) !!}</p>
            </div>
        @endif

        @if (! $member->quote && ! $member->bio)
            <p class="mt-8 text-sm text-ink-600">{{ __('Le message de :name sera bientôt publié.', ['name' => $member->name]) }}</p>
        @endif

        @if ($member->email)
            <a href="mailto:{{ $member->email }}" class="mt-8 inline-flex w-fit items-center gap-2.5 text-sm font-medium break-all text-royal-600 underline decoration-sun-400 decoration-2 underline-offset-4 hover:text-navy-900">
                <x-ico name="mail" class="size-4 text-sun-400" />{{ $member->email }}
            </a>
        @endif
    </div>
</div>
