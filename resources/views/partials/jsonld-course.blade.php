@php
    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $formation->title,
        'description' => strip_tags((string) ($formation->summary ?: $formation->description)),
        'url' => $formation->url(),
        'educationalLevel' => $formation->level->getLabel(),
        'timeRequired' => $formation->duration,
        'provider' => [
            '@type' => 'CollegeOrUniversity',
            'name' => 'Université Adventiste Zurcher',
            'sameAs' => url('/'),
        ],
        'hasCourseInstance' => array_filter([
            '@type' => 'CourseInstance',
            'courseMode' => 'onsite',
            'courseWorkload' => $formation->credits ? $formation->credits.' crédits ECTS' : null,
        ]),
    ], fn ($v) => filled($v));
@endphp
<script type="application/ld+json" @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
