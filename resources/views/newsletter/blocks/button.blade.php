@php($pad = $d['size'] === 'lg' ? '16px 34px' : '12px 26px')
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="{{ $d['align'] }}" style="{{ $d['align'] === 'center' ? 'margin:0 auto;' : '' }}">
    <tr>
        <td align="center" bgcolor="{{ $d['bg'] }}" style="background:{{ $d['bg'] }};border-radius:{{ $d['radius'] }}px;">
            <a href="{{ $d['url'] !== '' ? $d['url'] : url('/') }}" style="display:inline-block;padding:{{ $pad }};font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:{{ $d['size'] === 'lg' ? 17 : 15 }}px;font-weight:700;color:{{ $d['color'] }};text-decoration:none;border-radius:{{ $d['radius'] }}px;" {!! $ed('label') !!}>{{ $d['label'] }}</a>
        </td>
    </tr>
</table>
