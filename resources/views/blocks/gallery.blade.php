@php
    $images = collect($data['images'] ?? [])->map(fn ($i) => ['asset' => $assets->get($i['image_id'] ?? 0), 'caption' => $i['caption'] ?? null])->filter(fn ($i) => $i['asset']);
@endphp

@if ($images->isNotEmpty())
    <section class="bg-paper-100 py-12 md:py-16" x-data="lightbox">
        <div class="container-site">
            @if (! empty($data['title']))<h2 class="mb-8 text-h2" data-reveal>{{ $data['title'] }}</h2>@endif
            @include('partials.gallery-grid', ['photos' => $images->map(fn ($i) => ['asset' => $i['asset'], 'caption' => $i['caption'] ?? $i['asset']->caption]), 'dialogLabel' => $data['title'] ?? 'Galerie'])
        </div>
    </section>
@endif
