{{-- Bandeau : un fond de couleur qui empile ses blocs. Sur un fond sombre, le texte des blocs passe en clair tout seul. --}}
@php
    $scale = ['none' => [0, 0], 'sm' => [16, 20], 'md' => [28, 32], 'lg' => [44, 40]][$d['padding']] ?? [28, 32];
    [$padY, $padX] = $scale;
    $bg = $d['bg'];
    $dark = $bg !== '' && \App\Support\NewsletterRenderer::isDark($bg);
    $inner = max(120, $width - 2 * $padX);
    $childCtx = $dark ? array_replace($ctx, ['theme' => array_replace($ctx['theme'], ['text' => '#f1ede4', 'accent' => '#ffffff'])]) : $ctx;
    $radius = (int) $d['radius'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:separate;">
    <tr>
        <td class="nl-sec {{ $dark ? 'nl-dark' : '' }}" @if ($bg !== '') bgcolor="{{ $bg }}" @endif
            style="padding:{{ $padY }}px {{ $padX }}px;{{ $bg !== '' ? 'background:'.$bg.';' : '' }}{{ $radius > 0 ? 'border-radius:'.$radius.'px;' : '' }}"
            @if ($editing) data-nl-list data-nl-parent="{{ $block['id'] }}" data-nl-col="0" @endif>
            {!! \App\Support\NewsletterRenderer::nodes($block['children'][0], $depth + 1, $inner, $childCtx) !!}
            @if ($editing)
                <div data-nl-slot data-nl-parent="{{ $block['id'] }}" data-nl-col="0" data-nl-empty-slot="{{ $block['children'][0] === [] ? 1 : 0 }}">＋ Ajouter ici</div>
            @endif
        </td>
    </tr>
</table>
