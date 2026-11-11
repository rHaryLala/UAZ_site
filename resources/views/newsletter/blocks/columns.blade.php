{{-- Colonnes : un tableau de cellules aux largeurs exactes en pixels (la proportion et l'écart sont calculés ici), qui s'empilent sur mobile. --}}
@php
    $n = (int) $d['count'];
    $parts = array_map('intval', explode(':', $d['ratio']));
    $parts = count($parts) === $n ? $parts : array_fill(0, $n, 1);
    $gap = (int) $d['gap'];
    $room = max(120, $width - $gap * ($n - 1));
    $widths = array_map(fn (int $part) => (int) floor($room * $part / array_sum($parts)), $parts);
@endphp
<table role="presentation" class="nl-cols" width="{{ $width }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:{{ $width }}px;table-layout:fixed;">
    <tr>
        @foreach ($block['children'] as $c => $column)
            <td class="nl-col" width="{{ $widths[$c] }}" valign="{{ $d['valign'] }}" style="width:{{ $widths[$c] }}px;vertical-align:{{ $d['valign'] }};"
                @if ($editing) data-nl-list data-nl-parent="{{ $block['id'] }}" data-nl-col="{{ $c }}" @endif>
                {!! \App\Support\NewsletterRenderer::nodes($column, $depth + 1, $widths[$c], $ctx) !!}
                @if ($column === [] && ! $editing) &nbsp; @endif
                @if ($editing)
                    <div data-nl-slot data-nl-parent="{{ $block['id'] }}" data-nl-col="{{ $c }}" data-nl-empty-slot="{{ $column === [] ? 1 : 0 }}">＋ Ajouter ici</div>
                @endif
            </td>
            @if ($c < $n - 1 && $gap > 0)
                <td class="nl-gap" width="{{ $gap }}" style="width:{{ $gap }}px;font-size:0;line-height:0;">&nbsp;</td>
            @endif
        @endforeach
    </tr>
</table>
