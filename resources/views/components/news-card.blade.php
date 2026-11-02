@props(['post', 'eager' => false, 'level' => 'h3'])

<article {{ $attributes->class('card group flex h-full flex-col') }}>
    <div class="relative aspect-[3/2] overflow-hidden bg-navy-800">
        @if ($post->cover)
            <x-picture :asset="$post->cover" conversion="card" sizes="(min-width: 1024px) 28rem, (min-width: 640px) 45vw, 92vw" :eager="$eager"
                class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105" />
        @else
            <div class="flex size-full items-center justify-center text-navy-700/80"><x-madagascar class="h-3/4" /></div>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-6">
        <p class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold tracking-[0.14em] uppercase">
            @if ($post->category)
                <span class="text-gold-700">{{ $post->category->name }}</span>
            @endif
            <time datetime="{{ $post->published_at?->toDateString() }}" class="text-ink-600">{{ $post->published_at?->translatedFormat('j F Y') }}</time>
        </p>

        <{{ $level }} class="text-h4 font-serif">
            <a href="{{ $post->url() }}" class="stretched-link hover:text-navy-600">{{ $post->title }}</a>
        </{{ $level }}>

        @if ($post->excerpt)
            <p class="mt-3 line-clamp-3 text-sm text-ink-700">{{ $post->excerpt }}</p>
        @endif

        <span class="mt-auto inline-flex items-center gap-2 pt-5 text-sm font-semibold text-navy-800" aria-hidden="true">
            {{ __('Lire l\'article') }} <x-ico name="arrow-right" class="size-4 transition-transform duration-300 group-hover:translate-x-1" />
        </span>
    </div>
</article>
