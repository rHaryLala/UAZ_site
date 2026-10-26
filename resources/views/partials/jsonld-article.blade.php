@php
    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => strip_tags((string) $post->excerpt),
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'mainEntityOfPage' => $post->url(),
        'image' => $post->metaImage()?->url('hero'),
        'author' => ['@type' => 'Person', 'name' => $post->author?->name ?? 'Rédaction UAZ'],
        'publisher' => [
            '@type' => 'CollegeOrUniversity',
            'name' => 'Université Adventiste Zurcher',
            'logo' => ['@type' => 'ImageObject', 'url' => asset('images/uaz-seal-384.webp')],
        ],
    ], fn ($v) => filled($v));
@endphp
<script type="application/ld+json" @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
