@php($asset = $media->get($d['media']))
@php($src = \App\Support\NewsletterRenderer::imageUrl($asset))
@if ($src)
    @php($img = '<img src="'.e($src).'" alt="'.e((string) $asset->alt).'" style="display:block;width:'.($d['shadow'] !== '' ? '100' : $d['width']).'%;max-width:100%;height:auto;border-radius:'.$d['radius'].'px;'.($d['shadow'] === '' ? ($d['align'] === 'center' ? 'margin:0 auto;' : ($d['align'] === 'right' ? 'margin:0 0 0 auto;' : '')) : '').'">')
    @if ($d['link'] !== '')
        @php($img = '<a href="'.e($d['link']).'" style="text-decoration:none;">'.$img.'</a>')
    @endif
    @if ($d['shadow'] !== '')
        {{-- Ombre colorée décalée : une cellule colorée plus large que la photo, de 18 px à droite et en bas (le rectangle « derrière la photo »). --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:{{ $d['width'] }}%;max-width:100%;{{ ['left' => '', 'center' => 'margin:0 auto;', 'right' => 'margin:0 0 0 auto;'][$d['align']] }}">
            <tr>
                <td bgcolor="{{ $d['shadow'] }}" style="background:{{ $d['shadow'] }};padding:0 18px 18px 0;font-size:0;line-height:0;">{!! $img !!}</td>
            </tr>
        </table>
    @else
        {!! $img !!}
    @endif
@elseif ($editing)
    <div data-nl-placeholder data-nl-media="media" style="padding:46px 16px;border:2px dashed #c4c9d8;border-radius:8px;text-align:center;color:#6b7184;font-size:14px;">Cliquez ici pour choisir une photo</div>
@endif
