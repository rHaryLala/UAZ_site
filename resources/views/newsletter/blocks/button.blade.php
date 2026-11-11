{{-- Le bouton est posé dans une cellule alignée (et non « align » sur son tableau : cet attribut le fait flotter et le contenu suivant viendrait se placer à côté). --}}
@php($pad = $d['size'] === 'lg' ? '16px 34px' : '12px 26px')
@php($full = $d['full'] !== '')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
    <tr>
        <td align="{{ $d['align'] }}" style="text-align:{{ $d['align'] }};">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" @if ($full) width="100%" @endif style="{{ $full ? 'width:100%;' : ['left' => '', 'center' => 'margin:0 auto;', 'right' => 'margin:0 0 0 auto;'][$d['align']] }}">
                <tr>
                    <td align="center" bgcolor="{{ $d['bg'] }}" style="background:{{ $d['bg'] }};border-radius:{{ $d['radius'] }}px;">
                        <a href="{{ $d['url'] !== '' ? $d['url'] : url('/') }}" style="display:{{ $full ? 'block' : 'inline-block' }};padding:{{ $pad }};font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:{{ $d['size'] === 'lg' ? 17 : 15 }}px;font-weight:700;color:{{ $d['color'] }};text-decoration:none;border-radius:{{ $d['radius'] }}px;" {!! $ed('label') !!}>{{ $d['label'] }}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
