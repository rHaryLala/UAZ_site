@props([
    'title' => null,
    'description' => null,
    'image' => null, // MediaAsset ou URL absolue
    'canonical' => null,
    'type' => 'website',
    'noindex' => false,
])

@php
    $siteName = $site->site_name ?? config('app.name');
    $fullTitle = $title ? $title.' — '.$siteName : $siteName.($site->tagline ? ' — '.$site->tagline : '');
    $description ??= $site->tagline;
    $canonical ??= url()->current();
    $ogImage = $image instanceof \App\Models\MediaAsset
        ? $image->url('hero')
        : ($image ?: ($site->defaultOgImage ? $site->defaultOgImage->url('hero') : null));
@endphp

<title>{{ $fullTitle }}</title>
@if ($description)
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 170) }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@endif

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="fr_FR">
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
