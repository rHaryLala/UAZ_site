<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
    <x-page-hero :title="$page->title" :intro="$page->summary" :breadcrumbs="$trail" :image="$page->cover" />

    <x-blocks :blocks="$page->blocks" />

    @if ($children->isNotEmpty())
        <x-section tone="sand" title="Dans cette rubrique">
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($children as $child)
                    <li>
                        <article class="card group h-full p-7">
                            <h3 class="text-h4 font-serif"><a href="{{ $child->url() }}" class="stretched-link hover:text-navy-600">{{ $child->title }}</a></h3>
                            @if ($child->summary)<p class="mt-3 text-sm text-ink-700">{{ $child->summary }}</p>@endif
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-navy-800" aria-hidden="true">Consulter <x-ico name="arrow-right" class="size-4" /></span>
                        </article>
                    </li>
                @endforeach
            </ul>
        </x-section>
    @endif
</x-layouts.app>
