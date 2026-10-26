@php
    $sameAs = array_values(array_filter($site->social_links ?? []));
    $organization = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'CollegeOrUniversity',
        '@id' => url('/').'#organization',
        'name' => $site->site_name,
        'alternateName' => 'UAZ',
        'url' => url('/'),
        'logo' => asset('images/uaz-seal-384.webp'),
        'description' => $site->tagline,
        'email' => $site->email,
        'telephone' => $site->phone,
        'address' => $site->address ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $site->address,
            'addressLocality' => 'Antsirabe',
            'addressCountry' => 'MG',
        ]) : null,
        'geo' => ($site->latitude && $site->longitude) ? ['@type' => 'GeoCoordinates', 'latitude' => $site->latitude, 'longitude' => $site->longitude] : null,
        'sameAs' => $sameAs ?: null,
    ], fn ($v) => filled($v));

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $site->site_name,
        'url' => url('/'),
        'inLanguage' => 'fr',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => url('/recherche').'?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
@endphp
<script type="application/ld+json" @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! json_encode($organization, $flags) !!}</script>
<script type="application/ld+json" @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! json_encode($website, $flags) !!}</script>
