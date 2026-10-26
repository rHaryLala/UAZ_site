@props([])

@php
    // Silhouette vectorisée depuis le sceau (scripts/trace-madagascar.mjs) ; lue une seule fois par requête.
    static $shape = null;
    $shape ??= (function () {
        $svg = file_get_contents(resource_path('svg/madagascar.svg'));
        preg_match('/viewBox="([^"]+)"/', $svg, $vb);
        preg_match('/ d="([^"]+)"/', $svg, $d);

        return ['viewBox' => $vb[1], 'd' => $d[1]];
    })();
@endphp

<svg {{ $attributes->merge(['class' => 'text-current']) }} viewBox="{{ $shape['viewBox'] }}" fill="currentColor" aria-hidden="true" focusable="false">
    <path fill-rule="evenodd" d="{{ $shape['d'] }}"/>
</svg>
