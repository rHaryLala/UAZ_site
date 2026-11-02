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
    {{-- Textes affichés par les scripts (resources/js/i18n.js) : uniquement hors français, où ils sont déjà dans les scripts. --}}
    @unless (\App\Support\Locales::isDefault())
        <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>window.uazI18n = {!! json_encode(\App\Support\JsStrings::all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endunless

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
    <a href="#contenu" class="skip-link">{{ __('Aller au contenu principal') }}</a>

    <x-site.header />

    <main id="contenu" tabindex="-1">
        {{ $slot }}
    </main>

    <x-site.footer />

    {{-- Les mises à jour Livewire ont une adresse unique, sans préfixe de langue : la langue est rétablie par SetLocale (middleware persistant). --}}
    {!! \App\Support\Locales::withoutPrefix(fn () => \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scriptConfig()) !!}
    @stack('scripts')
</body>
</html>
