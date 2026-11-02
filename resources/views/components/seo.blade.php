@props([
    'title' => null,
    'description' => null,
    'image' => null, // MediaAsset ou URL absolue
    'canonical' => null,
    'type' => 'website',
    'noindex' => false,
])

@use('App\Support\Locales')

@php
    $siteName = $site->site_name ?? config('app.name');
    $fullTitle = $title ? $title.' — '.$siteName : $siteName.($site->tagline ? ' — '.$site->tagline : '');
    $description ??= $site->tagline;
    $canonical ??= request()->url();
    $ogImage = $image instanceof \App\Models\MediaAsset
        ? url($image->url('hero'))
        : ($image ?: ($site->defaultOgImage ? url($site->defaultOgImage->url('hero')) : null));
@endphp

<title>{{ $fullTitle }}</title>
@if ($description)
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 170) }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
@unless ($noindex)
    {{-- Versions du site dans les autres langues (adresses identiques au préfixe près). --}}
    @foreach (array_keys(Locales::all()) as $code)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ Locales::urlFor($code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ Locales::urlFor(Locales::default()) }}">
@endunless
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@endif

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ Locales::og() }}">
@foreach (array_keys(Locales::all()) as $code)
    @if ($code !== Locales::current())
        <meta property="og:locale:alternate" content="{{ config('uaz.locales.'.$code.'.og') }}">
    @endif
@endforeach
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title ?: $siteName }}">
@if ($description)
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200) }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $title ?: $siteName }}">
@if ($description)
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200) }}">
@endif
@if ($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
