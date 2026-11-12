{{-- Le texte de « Photo en fond » : surtitre, titre, texte, bouton. Variables héritées de hero.blade.php : $d, $ed, $headingFont, $ink, $accent, $panelBg, $align. --}}
@if ($d['eyebrow'] !== '' || $editing)
    <div style="margin:0 0 12px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:.18em;line-height:1.4;text-transform:uppercase;color:{{ $accent }};text-align:{{ $align }};" {!! $ed('eyebrow') !!}>{{ $d['eyebrow'] }}</div>
@endif
<div style="margin:0;font-family:{{ $headingFont }};font-size:{{ $layout === 'bottom' ? 30 : 36 }}px;font-weight:700;line-height:1.08;color:{{ $ink }};text-align:{{ $align }};" {!! $ed('title') !!}>{{ $d['title'] }}</div>
@if ($d['text'] !== '' || $editing)
    <div style="margin:14px 0 0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:{{ $ink }};text-align:{{ $align }};" {!! $ed('text') !!}>{{ $d['text'] }}</div>
@endif
@if ($d['button_label'] !== '' || $editing)
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px {{ $align === 'center' ? 'auto' : '0' }} 0 {{ $align === 'center' ? 'auto' : '0' }};">
        <tr>
            <td bgcolor="{{ $accent }}" style="background:{{ $accent }};">
                <a href="{{ $d['button_url'] !== '' ? $d['button_url'] : url('/') }}" style="display:inline-block;padding:12px 24px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{{ $panelBg }};text-decoration:none;" {!! $ed('button_label') !!}>{{ $d['button_label'] }}</a>
            </td>
        </tr>
    </table>
@endif
