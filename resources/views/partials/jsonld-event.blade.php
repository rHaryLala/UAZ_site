@php
    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event->title,
        'description' => strip_tags((string) ($event->summary ?: $event->description)),
        'startDate' => $event->starts_at->toIso8601String(),
        'endDate' => $event->ends_at?->toIso8601String(),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'url' => $event->url(),
        'image' => $event->metaImage()?->url('hero'),
        'location' => $event->location ? ['@type' => 'Place', 'name' => $event->location] : null,
        'organizer' => ['@type' => 'CollegeOrUniversity', 'name' => 'Université Adventiste Zurcher', 'url' => url('/')],
    ], fn ($v) => filled($v));
@endphp
<script type="application/ld+json" @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
