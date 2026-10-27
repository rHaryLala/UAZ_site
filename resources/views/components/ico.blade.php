@props(['name', 'stroke' => 1.75])

@php
    // Pictogrammes inline (24×24, trait). Décoratifs : aria-hidden ; le texte accessible vient du contexte.
    $icons = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'menu' => '<path d="M4 8h16M4 16h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'map-pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M5 20h14"/>',
        'document' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
        'play' => '<path d="M8 5v14l11-7z" fill="currentColor"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v6h4v-6h3l1-4h-4V8.5a.5.5 0 0 1 .5-.5z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'linkedin' => '<path d="M6 9v10M6 5.5v.01M10 19V9m0 4a4 4 0 0 1 8 0v6"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
        'cloud' => '<path d="M7 19a4 4 0 0 1-.5-8A5.5 5.5 0 0 1 17 9.5 4.8 4.8 0 0 1 17.5 19z"/>',
        'cloud-sun' => '<circle cx="8" cy="8" r="2.5"/><path d="M8 2v1.5M2 8h1.5M3.8 3.8l1 1M12.2 3.8l-1 1"/><path d="M9 20a3.5 3.5 0 0 1-.4-7A4.8 4.8 0 0 1 18 12.5 3.8 3.8 0 0 1 18.5 20z"/>',
        'fog' => '<path d="M7 14a4 4 0 0 1-.5-8A5.5 5.5 0 0 1 17 4.5 4.8 4.8 0 0 1 17.5 14z"/><path d="M5 18h14M8 21h8"/>',
        'rain' => '<path d="M7 15a4 4 0 0 1-.5-8A5.5 5.5 0 0 1 17 5.5 4.8 4.8 0 0 1 17.5 15z"/><path d="M8 18l-1 3M12 18l-1 3M16 18l-1 3"/>',
        'storm' => '<path d="M7 14a4 4 0 0 1-.5-8A5.5 5.5 0 0 1 17 4.5 4.8 4.8 0 0 1 17.5 14z"/><path d="m12 13-2.5 4.5h3L10 22"/>',
        'grid' =>'<rect x="4" y="4" width="7" height="7"/><rect x="13" y="4" width="7" height="7"/><rect x="4" y="13" width="7" height="7"/><rect x="13" y="13" width="7" height="7"/>',
        'expand' => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
        'heart' =>'<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
        'tiktok' =>'<path d="M15 3v11.2a4.2 4.2 0 1 1-4.2-4.2"/><path d="M15 3c.3 2.3 1.9 4 4.4 4.3"/>',
        'youtube' =>'<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $icons[$name] ?? '' !!}</svg>
