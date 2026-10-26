@props(['items' => [], 'dark' => false])

{{-- Fil d'Ariane : [['label' => 'Formations', 'url' => '/formations'], ['label' => 'Licence en Droit']] ; le dernier est la page courante. --}}
@php
    $crumbs = collect([['label' => 'Accueil', 'url' => url('/')], ...$items])->values();
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $crumbs->map(fn ($c, $i) => array_filter([
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $c['label'],
            'item' => $c['url'] ?? ($i === $crumbs->count() - 1 ? url()->current() : null),
        ]))->all(),
    ];
@endphp

<nav aria-label="Fil d'Ariane" {{ $attributes }}>
    <ol @class(['flex flex-wrap items-center gap-x-2 gap-y-1 text-sm', 'text-navy-100' => $dark, 'text-ink-600' => ! $dark])>
        <li><a href="{{ url('/') }}" @class(['underline-offset-4 hover:underline', 'hover:text-white' => $dark, 'hover:text-navy-800' => ! $dark])>Accueil</a></li>
        @foreach ($items as $item)
            <li class="flex items-center gap-2">
                <x-ico name="chevron-right" class="size-3.5 opacity-60" />
                @if (! $loop->last && ! empty($item['url']))
                    <a href="{{ $item['url'] }}" @class(['underline-offset-4 hover:underline', 'hover:text-white' => $dark, 'hover:text-navy-800' => ! $dark])>{{ $item['label'] }}</a>
                @else
                    <span aria-current="page" @class(['font-medium', 'text-white' => $dark, 'text-ink-900' => ! $dark])>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
    <script type="application/ld+json" @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</nav>
