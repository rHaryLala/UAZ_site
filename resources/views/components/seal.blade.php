@props(['size' => 64, 'eager' => false])

{{-- Sceau de l'UAZ : jamais déformé (largeur = hauteur, object-contain). --}}
<img
    src="{{ asset('images/uaz-seal-192.webp') }}"
    srcset="{{ asset('images/uaz-seal-96.webp') }} 96w, {{ asset('images/uaz-seal-192.webp') }} 192w, {{ asset('images/uaz-seal-384.webp') }} 384w, {{ asset('images/uaz-seal-768.webp') }} 768w"
    sizes="{{ $size }}px"
    width="{{ $size }}"
    height="{{ $size }}"
    alt="Sceau de l'Université Adventiste Zurcher"
    @if ($eager) fetchpriority="high" @else loading="lazy" @endif
    decoding="async"
    {{ $attributes->merge(['class' => 'object-contain']) }}
>
