@props(['member', 'eager' => false])

<article {{ $attributes->class('group') }}>
    <div class="relative aspect-[4/5] overflow-hidden rounded-card bg-navy-800">
        @if ($member->photo)
            <x-picture :asset="$member->photo" conversion="card" sizes="(min-width: 1024px) 18rem, (min-width: 640px) 30vw, 46vw" :eager="$eager"
                class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105" />
        @else
            {{-- Sans photo : initiales sur fond marine, avec la silhouette de Madagascar en filigrane --}}
            <x-madagascar class="absolute inset-y-4 right-0 h-[90%] text-navy-700/70" />
            <span class="absolute bottom-4 left-5 font-serif text-5xl text-gold-300" aria-hidden="true">
                {{ collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </span>
        @endif
    </div>
    <h3 class="mt-4 font-serif text-xl text-navy-800">{{ $member->name }}</h3>
    @if ($member->job_title)
        <p class="mt-1 text-sm font-medium text-gold-700">{{ $member->job_title }}</p>
    @endif
    @if ($member->faculty)
        <p class="text-sm text-ink-600">{{ $member->faculty->name }}</p>
    @endif
    @if ($member->email)
        <p class="mt-2 text-sm"><a href="mailto:{{ $member->email }}" class="break-all text-ink-700 underline decoration-gold-500 decoration-2 underline-offset-4 hover:text-navy-800">{{ $member->email }}</a></p>
    @endif
</article>
