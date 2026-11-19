@php($rows = $pages->values())
<div style="font-size:.875rem">
    <p style="margin:0 0 .75rem; opacity:.75">
        {{ $visit->device === 'mobile' ? 'Téléphone' : ($visit->device === 'tablet' ? 'Tablette' : 'Ordinateur') }}{{ $visit->browser ? ' · '.$visit->browser : '' }}
        · {{ $rows->count() }} {{ $rows->count() > 1 ? 'pages vues' : 'page vue' }}
        @if ($rows->count() > 1)
            · de {{ $rows->first()->visited_at->format('H:i') }} à {{ $rows->last()->visited_at->format('H:i') }}
        @endif
    </p>
    <ol style="margin:0; padding:0; list-style:none">
        @foreach ($rows as $page)
            <li style="display:flex; gap:.75rem; align-items:baseline; padding:.45rem 0; border-top:1px solid color-mix(in srgb, currentColor 12%, transparent)">
                <span style="flex:none; width:3rem; font-variant-numeric:tabular-nums; opacity:.65">{{ $page->visited_at->format('H:i:s') }}</span>
                <span style="min-width:0; flex:1">
                    <a href="{{ url($page->path) }}" target="_blank" rel="noopener" style="color:inherit; word-break:break-all">{{ $page->path }}</a>
                    @if ($loop->first && $page->referrer)
                        <span style="display:block; font-size:.75rem; opacity:.65">Venu de {{ $page->referrer }}</span>
                    @elseif ($loop->first)
                        <span style="display:block; font-size:.75rem; opacity:.65">Accès direct</span>
                    @endif
                </span>
                <span style="flex:none; font-size:.75rem; opacity:.55">{{ strtoupper($page->locale) }}</span>
            </li>
        @endforeach
    </ol>
</div>
