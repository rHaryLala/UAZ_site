{{--
    Mosaïque (« bento ») : des photos de tailles différentes assemblées en grille. Les messageries ne recadrent pas les images : chaque case reçoit une photo
    DÉJÀ découpée à sa taille exacte (NewsletterRenderer::crop). Les largeurs sont en pixels pour Outlook et en pourcentage pour les messageries fluides.
    Une case sans photo reste une case de couleur : la grille ne se casse pas.
--}}
@php
    $W = max(200, (int) $width);
    $g = (int) $d['gap'];
    $H = (int) $d['height'];
    $layout = array_key_exists($d['layout'], \App\Support\NewsletterBlocks::MOSAIC_LAYOUTS) ? $d['layout'] : '1-2';
    $frame = $d['frame'] !== '' ? 3 : 0;
    $radius = (int) $d['radius'];
    $keys = \App\Support\NewsletterBlocks::MOSAIC_MEDIA;
    $pct = fn (int $px): string => rtrim(rtrim(number_format($px / $W * 100, 2, '.', ''), '0'), '.').'%';

    // Une case : cadre éventuel, photo découpée à la taille intérieure, lien éventuel. $i = rang de la photo (0, 1, 2…).
    $tile = function (int $i, int $w, int $h, string $valign = 'top') use ($d, $media, $keys, $frame, $radius, $editing, $pct): string {
        $field = $keys[$i] ?? 'media';
        $asset = $media->get($d[$field] ?? 0);
        [$iw, $ih] = [$w - 2 * $frame, $h - 2 * $frame];
        $src = $asset ? \App\Support\NewsletterRenderer::crop($asset, $iw, $ih) : null;
        $cell = 'width="'.$w.'" height="'.$h.'" valign="'.$valign.'" align="center" style="width:'.$pct($w).';height:'.$h.'px;vertical-align:top;font-size:0;line-height:0;';

        if (! $src) {
            $inner = $editing
                ? '<div data-nl-placeholder data-nl-media="'.$field.'" style="height:'.($ih - 4).'px;line-height:'.($ih - 4).'px;border:2px dashed #7b86b4;border-radius:6px;background:#eef0f8;color:#4a5280;font-family:Helvetica,Arial,sans-serif;font-size:13px;text-align:center;">🖼 Photo '.($i + 1).'</div>'
                : '&nbsp;';

            return '<td '.$cell.'background:'.e($d['tile_bg']).';border-radius:'.$radius.'px;" bgcolor="'.e($d['tile_bg']).'">'.$inner.'</td>';
        }

        $img = '<img class="nl-tile" src="'.e($src).'" width="'.$iw.'" height="'.$ih.'" alt="'.e((string) $asset->alt).'" style="display:block;width:100%;height:auto;border:0;border-radius:'.max(0, $radius - $frame).'px;"'.($editing ? ' data-nl-media="'.$field.'"' : '').'>';

        if ($d['link'] !== '' && ! $editing) {
            $img = '<a href="'.e($d['link']).'" style="text-decoration:none;">'.$img.'</a>';
        }

        if ($frame) {
            return '<td '.$cell.'padding:'.$frame.'px;background:'.e($d['frame']).';border-radius:'.$radius.'px;" bgcolor="'.e($d['frame']).'">'.$img.'</td>';
        }

        return '<td '.$cell.'">'.$img.'</td>';
    };

    $gapCol = '<td class="nl-tilegap" width="'.$g.'" style="width:'.$g.'px;font-size:0;line-height:0;">&nbsp;</td>';
    $gapRow = fn (int $cols): string => '<tr><td colspan="'.$cols.'" height="'.$g.'" style="height:'.$g.'px;font-size:0;line-height:0;">&nbsp;</td></tr>';
    $table = fn (string $rows, int $width = 0): string => '<table role="presentation" width="'.($width ?: $W).'" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:'.($width ?: $W).'px;">'.$rows.'</table>';
@endphp
@switch($layout)
    @case('3')
        @php($w = (int) floor(($W - 2 * $g) / 3))
        @php($last = $W - 2 * $g - 2 * $w)
        {!! $table('<tr>'.$tile(0, $w, $w).$gapCol.$tile(1, $w, $w).$gapCol.$tile(2, $last, $w).'</tr>') !!}
        @break

    @case('2x2')
        @php($w = (int) floor(($W - $g) / 2))
        @php($last = $W - $g - $w)
        @php($hh = (int) round($w * 0.72))
        {!! $table('<tr>'.$tile(0, $w, $hh).$gapCol.$tile(1, $last, $hh).'</tr>'.$gapRow(3).'<tr>'.$tile(2, $w, $hh).$gapCol.$tile(3, $last, $hh).'</tr>') !!}
        @break

    @case('1-3')
        @php($top = (int) round($W * 0.5))
        @php($w = (int) floor(($W - 2 * $g) / 3))
        @php($last = $W - 2 * $g - 2 * $w)
        {!! $table('<tr>'.$tile(0, $W, $top).'</tr>'.$gapRow(1).'<tr><td style="font-size:0;line-height:0;">'.$table('<tr>'.$tile(1, $w, $w).$gapCol.$tile(2, $w, $w).$gapCol.$tile(3, $last, $w).'</tr>').'</td></tr>') !!}
        @break

    @case('2-1')
        @php($big = (int) floor(($W - $g) * 2 / 3))
        @php($small = $W - $g - $big)
        @php($half = (int) floor(($H - $g) / 2))
        @php($stack = $table('<tr>'.$tile(0, $small, $half).'</tr>'.$gapRow(1).'<tr>'.$tile(1, $small, $H - $g - $half).'</tr>', $small))
        {!! $table('<tr><td width="'.$small.'" valign="top" style="width:'.$pct($small).';vertical-align:top;font-size:0;line-height:0;">'.$stack.'</td>'.$gapCol.$tile(2, $big, $H).'</tr>') !!}
        @break

    @default
        {{-- « 1-2 » : une grande case à gauche, deux cases empilées à droite. --}}
        @php($big = (int) floor(($W - $g) * 2 / 3))
        @php($small = $W - $g - $big)
        @php($half = (int) floor(($H - $g) / 2))
        @php($stack = $table('<tr>'.$tile(1, $small, $half).'</tr>'.$gapRow(1).'<tr>'.$tile(2, $small, $H - $g - $half).'</tr>', $small))
        {!! $table('<tr>'.$tile(0, $big, $H).$gapCol.'<td width="'.$small.'" valign="top" style="width:'.$pct($small).';vertical-align:top;font-size:0;line-height:0;">'.$stack.'</td></tr>') !!}
@endswitch
