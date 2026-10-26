<x-section tone="white" :eyebrow="__('home.news.eyebrow')" :title="__('home.news.title')">
    <ul class="grid gap-6 md:grid-cols-3">
        @foreach ($news as $post)
            <li data-reveal data-reveal-delay="{{ $loop->index * 0.1 }}"><x-news-card :post="$post" /></li>
        @endforeach
    </ul>
    <div class="mt-10 text-center" data-reveal>
        <x-button :href="url('/actualites')" variant="outline" arrow>{{ __('home.news.link') }}</x-button>
    </div>
</x-section>
