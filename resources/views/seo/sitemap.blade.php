<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
@foreach ($locales as $code)
    <url>
        <loc>{{ \App\Support\Locales::localize($entry['url'], $code) }}</loc>
@foreach ($locales as $alt)
        <xhtml:link rel="alternate" hreflang="{{ $alt }}" href="{{ \App\Support\Locales::localize($entry['url'], $alt) }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::localize($entry['url'], \App\Support\Locales::default()) }}"/>
@if (! empty($entry['lastmod']))
        <lastmod>{{ $entry['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $entry['freq'] }}</changefreq>
        <priority>{{ $entry['priority'] }}</priority>
    </url>
@endforeach
@endforeach
</urlset>
