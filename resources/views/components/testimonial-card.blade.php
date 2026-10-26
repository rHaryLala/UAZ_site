@props(['testimonial'])

<figure {{ $attributes->class('relative') }}>
    @if ($testimonial->quote)
        <blockquote class="font-serif text-h3 leading-snug text-white">
            <span class="mr-1 align-top text-gold-300" aria-hidden="true">«</span>{{ $testimonial->quote }}<span class="ml-1 text-gold-300" aria-hidden="true">»</span>
        </blockquote>
    @endif

    <figcaption class="mt-8 flex items-center gap-4">
        @if ($testimonial->photo)
            <x-picture :asset="$testimonial->photo" conversion="thumb" sizes="64px" decorative class="size-16 rounded-full object-cover ring-2 ring-gold-300/60" />
        @endif
        <div>
            <p class="font-semibold text-white">{{ $testimonial->author_name }}</p>
            @if ($testimonial->author_role)
                <p class="text-sm text-navy-100">{{ $testimonial->author_role }}</p>
            @endif
        </div>
        @if ($testimonial->video_url)
            <a href="{{ $testimonial->video_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm ml-auto">
                <x-ico name="play" class="size-4" /> Voir la vidéo <span class="sr-only">de {{ $testimonial->author_name }} (nouvel onglet)</span>
            </a>
        @endif
    </figcaption>
</figure>
