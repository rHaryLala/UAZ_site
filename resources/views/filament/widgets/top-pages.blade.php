<x-filament-widgets::widget>
    <x-filament::section heading="Pages les plus vues" description="30 derniers jours">
        @if (count($pages) === 0)
            <p style="opacity:.7; font-size:.875rem">Aucune visite comptée pour l'instant.</p>
        @else
            <table style="width:100%; border-collapse:collapse; font-size:.875rem">
                <thead>
                    <tr style="text-align:left; opacity:.65; font-size:.75rem">
                        <th style="padding:.25rem 0; font-weight:500">Page</th>
                        <th style="padding:.25rem 0 .25rem .75rem; font-weight:500; text-align:right">Vues</th>
                        <th style="padding:.25rem 0 .25rem .75rem; font-weight:500; text-align:right">Visiteurs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr style="border-top:1px solid color-mix(in srgb, currentColor 12%, transparent)">
                            <td style="padding:.4rem 0; max-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                                <a href="{{ url($page['path']) }}" target="_blank" rel="noopener" style="text-decoration:none; color:inherit" title="{{ $page['path'] }}">{{ $page['path'] }}</a>
                            </td>
                            <td style="padding:.4rem 0 .4rem .75rem; text-align:right; font-variant-numeric:tabular-nums">{{ number_format($page['views'], 0, ',', ' ') }}</td>
                            <td style="padding:.4rem 0 .4rem .75rem; text-align:right; font-variant-numeric:tabular-nums">{{ number_format($page['visitors'], 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (count($places) > 0)
            <p style="margin:1rem 0 .35rem; font-size:.75rem; font-weight:600; opacity:.65">D'où se connectent les visiteurs <span style="font-weight:400">(approximatif, d'après l'adresse IP)</span></p>
            <ul style="margin:0; padding:0; list-style:none; font-size:.875rem">
                @foreach ($places as $place)
                    <li style="display:flex; justify-content:space-between; gap:1rem; padding:.2rem 0">
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ $place['place'] }}</span>
                        <span style="font-variant-numeric:tabular-nums">{{ number_format($place['visitors'], 0, ',', ' ') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (count($referrers) > 0)
            <p style="margin:1rem 0 .35rem; font-size:.75rem; font-weight:600; opacity:.65">D'où viennent les visiteurs</p>
            <ul style="margin:0; padding:0; list-style:none; font-size:.875rem">
                @foreach ($referrers as $referrer)
                    <li style="display:flex; justify-content:space-between; gap:1rem; padding:.2rem 0">
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ $referrer['host'] }}</span>
                        <span style="font-variant-numeric:tabular-nums">{{ number_format($referrer['views'], 0, ',', ' ') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (auth()->user()?->can('visitors.view'))
            <p style="margin:1rem 0 0; font-size:.875rem">
                <a href="{{ \App\Filament\Resources\SiteVisits\SiteVisitResource::getUrl() }}" wire:navigate style="color:var(--uaz-accent, var(--primary-600)); font-weight:600; text-decoration:none">Voir chaque visiteur, son lieu et ses pages →</a>
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
