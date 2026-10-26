<x-layouts.app :title="$post->metaTitle()" :description="$post->metaDescription()" :image="$post->metaImage()" type="article" :noindex="! $post->isLive()">
    <x-page-hero :eyebrow="$post->category?->name ?? 'Actualités'" :title="$post->title" :intro="$post->excerpt"
        :breadcrumbs="[['label' => 'Actualités', 'url' => url('/actualites')], ['label' => str($post->title)->limit(48)->toString()]]">
        <p class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-navy-100">
            <span class="inline-flex items-center gap-2"><x-ico name="calendar" class="size-4 text-gold-300" /><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></span>
            @if ($post->author)<span>Par {{ $post->author->name }}</span>@endif
        </p>
    </x-page-hero>

    <article class="bg-paper-100 py-14 md:py-20">
        @if ($post->cover)
            <figure class="container-site mb-12 max-w-5xl md:mb-16">
                <x-picture :asset="$post->cover" conversion="hero" sizes="(min-width: 1024px) 64rem, 100vw" eager class="h-auto w-full rounded-card shadow-card" />
                @if ($post->cover->caption || $post->cover->credit)
                    <figcaption class="mt-3 text-sm text-ink-600">{{ $post->cover->caption }} @if ($post->cover->credit)<span class="text-ink-500">© {{ $post->cover->credit }}</span>@endif</figcaption>
                @endif
            </figure>
        @endif

        <div class="container-prose"><x-rich :html="$post->body" /></div>

        <div class="container-prose mt-14 border-t border-paper-300 pt-8">
            <a href="{{ url('/actualites') }}" class="link-arrow"><x-ico name="arrow-left" class="size-4" /> Toutes les actualités</a>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <x-section tone="sand" title="À lire aussi">
            <ul class="grid gap-6 md:grid-cols-3">
                @foreach ($related as $item)
                    <li><x-news-card :post="$item" /></li>
                @endforeach
            </ul>
        </x-section>
    @endif

    @push('head')
        @include('partials.jsonld-article', ['post' => $post])
    @endpush
</x-layouts.app>
