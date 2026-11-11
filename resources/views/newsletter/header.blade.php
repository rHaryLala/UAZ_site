{{--
    En-tête de la lettre : un bandeau de couleur dont on règle la hauteur, avec quatre éléments que l'on place à gauche, au centre ou à droite
    (ou que l'on masque) : le logo (sceau de l'UAZ), le nom de l'université, un hashtag et les icônes des réseaux sociaux.
    $theme : le thème validé ; $dark : le fond est sombre (texte et icônes en blanc) ; $editing : repères de modification du plan de travail.
--}}
@php
    $inner = \App\Support\NewsletterRenderer::WIDTH - 56; // largeur utile du bandeau (marges de 28 px de chaque côté)
    $fg = $dark ? '#ffffff' : '#1d2233';
    $accent = $dark ? '#e0b957' : $theme['gold'];
    $variant = $dark ? 'white' : 'dark';
    $height = (int) $theme['header_height'];
    $logo = (int) $theme['header_logo_size'];
    $networks = collect(\App\Support\NewsletterBlocks::SOCIALS)->filter(fn (string $label, string $network) => $theme['social_'.$network] !== '');
    $count = max(1, $networks->count());
    $icon = 22; // toutes les icônes ont la même taille et le même écart (les PNG sont normalisés sur la taille visible d'Instagram)
    $gap = 10;

    // Place estimée de chaque élément (px), pour répartir les trois zones.
    $room = [
        'logo' => $logo,
        'name' => 170,
        'tag' => max(40, mb_strlen($theme['header_tag_text']) * 12 + 8),
        'social' => $count * $icon + ($count - 1) * $gap,
    ];

    // Qui va où : un élément sans contenu (hashtag vide, aucun réseau renseigné) n'occupe pas de place dans le courrier envoyé.
    $slots = ['left' => [], 'center' => [], 'right' => []];
    foreach (array_keys(\App\Support\NewsletterBlocks::HEADER_ITEMS) as $item) {
        $slot = $theme['header_'.$item];
        $empty = ($item === 'tag' && $theme['header_tag_text'] === '' && ! $editing) || ($item === 'social' && $networks->isEmpty());
        if ($slot !== '' && ! $empty) {
            $slots[$slot][] = $item;
        }
    }
    $need = fn (string $slot): int => array_sum(array_map(fn (string $item) => $room[$item], $slots[$slot])) + 14 * max(0, count($slots[$slot]) - 1);

    // Avec un élément au centre, les deux côtés sont de même largeur (le centre est vraiment centré) ; sinon seulement les côtés utilisés.
    if ($slots['center'] !== []) {
        $side = (int) max(60, min(max($need('left'), $need('right')), floor(($inner - $need('center')) / 2)));
        $widths = ['left' => $side, 'center' => $inner - 2 * $side, 'right' => $side];
        $cells = ['left', 'center', 'right'];
    } else {
        $cells = array_keys(array_filter($slots));
        $widths = array_fill_keys($cells, $cells === [] ? 0 : (int) floor($inner / count($cells)));
    }
    $marks = $editing ? 'data-nl-hdr' : '';
@endphp
@if ($theme['header'])
<tr {!! $marks !!}>
    <td class="nl-hd" bgcolor="{{ $theme['header_color'] }}" height="{{ $height }}" valign="middle" @if ($editing) data-nl-header @endif
        style="background:{{ $theme['header_color'] }};height:{{ $height }}px;padding:0 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
            <tr>
                @forelse ($cells as $slot)
                    @php
                        // Trop d'éléments pour la zone : on les empile (un par ligne) plutôt que de les laisser déborder.
                        $stacked = $need($slot) > $widths[$slot];
                    @endphp
                    <td class="nl-hd-cell" width="{{ $widths[$slot] }}" align="{{ $slot }}" valign="middle" style="width:{{ $widths[$slot] }}px;vertical-align:middle;text-align:{{ $slot }};">
                        @if ($slots[$slot] !== [])
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="{{ ['left' => '', 'center' => 'margin:0 auto;', 'right' => 'margin:0 0 0 auto;'][$slot] }}">
                                @unless ($stacked) <tr> @endunless
                                    @foreach ($slots[$slot] as $i => $item)
                                        @if ($stacked) <tr> @endif
                                        <td valign="middle" align="{{ $slot }}" style="vertical-align:middle;text-align:{{ $slot }};{{ $i > 0 ? ($stacked ? 'padding-top:10px;' : 'padding-left:14px;') : '' }}">
                                            @if ($item === 'logo')
                                                <img class="nl-hd-logo" src="{{ asset('images/uaz-seal-384.png') }}" width="{{ $logo }}" height="{{ $logo }}" alt="Université Adventiste Zurcher" style="display:block;border:0;width:{{ $logo }}px;height:{{ $logo }}px;border-radius:50%;{{ ['left' => '', 'center' => 'margin:0 auto;', 'right' => 'margin:0 0 0 auto;'][$slot] }}">
                                            @elseif ($item === 'name')
                                                <div style="font-family:{{ $headingFont }};color:{{ $fg }};font-size:17px;line-height:1.25;letter-spacing:.02em;white-space:nowrap;">Université Adventiste<br><span style="color:{{ $accent }};">Zurcher</span></div>
                                            @elseif ($item === 'tag')
                                                <div style="font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-weight:800;font-size:18px;line-height:1.3;color:{{ $fg }};white-space:nowrap;" @if ($editing) data-nl-theme-field="header_tag_text" @endif>{{ $theme['header_tag_text'] }}</div>
                                            @else
                                                <div style="white-space:nowrap;font-size:0;line-height:0;">
                                                    @foreach ($networks as $network => $label)
                                                        <a href="{{ $theme['social_'.$network] }}" style="text-decoration:none;"><img class="nl-hd-ico" src="{{ asset('images/newsletter/'.$network.'-'.$variant.'.png') }}" width="{{ $icon }}" height="{{ $icon }}" alt="{{ $label }}" style="display:inline-block;border:0;width:{{ $icon }}px;height:{{ $icon }}px;{{ $loop->first ? '' : 'margin-left:'.$gap.'px;' }}"></a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        @if ($stacked) </tr> @endif
                                    @endforeach
                                @unless ($stacked) </tr> @endunless
                            </table>
                        @else
                            &nbsp;
                        @endif
                    </td>
                @empty
                    <td>&nbsp;</td>
                @endforelse
            </tr>
        </table>
    </td>
</tr>
@if ($theme['header_rule'])
    <tr {!! $marks !!}><td style="height:3px;line-height:3px;font-size:3px;background:{{ $theme['gold'] }};">&nbsp;</td></tr>
@endif
@endif
@if (! $theme['header'] || $theme['header_rule'])
    <tr {!! $marks !!}><td style="height:10px;line-height:10px;font-size:10px;">&nbsp;</td></tr>
@endif
