@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'canonical' => null,
    'type' => 'website',
    'noindex' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#232E66">

    {{-- Classe « js » posée avant le premier rendu : les états initiaux d'animation ne s'appliquent qu'avec JavaScript. --}}
    <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>document.documentElement.classList.add('js')</script>

    <x-seo :title="$title" :description="$description" :image="$image" :canonical="$canonical" :type="$type" :noindex="$noindex" />

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Polices auto-hébergées : préchargées (latin variable), font-display: swap dans fonts.css --}}
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource-variable/libre-baskerville/files/libre-baskerville-latin-wght-normal.woff2') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body {{ $attributes->class('min-h-screen antialiased') }}>
    <a href="#contenu" class="skip-link">Aller au contenu principal</a>

    <x-site.header />

    <main id="contenu" tabindex="-1">
        {{ $slot }}
    </main>

    <x-site.footer />

    @livewireScriptConfig
    @stack('scripts')
</body>
</html>
