@props([
    'asset' => null,
    'conversion' => 'card',
    'sizes' => '100vw',
    'alt' => null,
    'decorative' => false,
    'eager' => false,
])

{{--
    Image responsive de la médiathèque : AVIF puis WebP en srcset, dimensions explicites (anti-CLS),
    lazy-loading sauf pour les images « eager » (héros, image principale de page).
--}}
@if ($asset && $asset->file())
    @php($d = $asset->pictureData($conversion))
    <picture>
        @if ($d['avif'])
            <source type="image/avif" srcset="{{ $d['avif'] }}" sizes="{{ $sizes }}">
        @endif
        @if ($d['webp'])
            <source type="image/webp" srcset="{{ $d['webp'] }}" sizes="{{ $sizes }}">
        @endif
        <img
            src="{{ $d['src'] }}"
            @if ($d['width']) width="{{ $d['width'] }}" height="{{ $d['height'] }}" @endif
            alt="{{ $decorative ? '' : ($alt ?? $asset->alt) }}"
            @if ($eager) fetchpriority="high" decoding="sync" @else loading="lazy" decoding="async" @endif
            {{ $attributes }}
        >
    </picture>
@endif
