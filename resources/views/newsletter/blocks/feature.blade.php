@php($asset = $media->get($d['media']))
@php($src = \App\Support\NewsletterRenderer::imageUrl($asset))
@php($imageCell = '<td class="nl-col" width="38%" valign="top" style="width:38%;'.($d['side'] === 'left' ? 'padding:0 22px 0 0;' : 'padding:0 0 0 22px;').'">'.($src ? '<img src="'.e($src).'" alt="'.e((string) $asset->alt).'" style="display:block;width:100%;height:auto;border-radius:6px;">' : ($editing ? '<div data-nl-placeholder style="padding:40px 8px;border:2px dashed #c4c9d8;border-radius:8px;text-align:center;color:#6b7184;font-size:13px;">Cliquez pour choisir une photo</div>' : '')).'</td>')
@php($textCell = '<td class="nl-col" valign="top" style="padding:0;">')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        @if ($d['side'] === 'left') {!! $imageCell !!} @endif
        {!! $textCell !!}
            <div style="margin:0 0 8px;font-family:{{ $headingFont }};font-weight:700;font-size:22px;line-height:1.25;color:{{ $theme['accent'] }};" {!! $ed('title') !!}>{{ $d['title'] }}</div>
            <div class="nl-text" style="font-size:15px;line-height:1.6;color:{{ $theme['text'] }};" {!! $ed('html') !!}>{!! $d['html'] !!}</div>
            @if ($d['link'] !== '')
                <div style="margin-top:12px;"><a href="{{ $d['link'] }}" style="font-size:14px;font-weight:700;color:{{ $theme['accent'] }};">Lire la suite →</a></div>
            @endif
        </td>
        @if ($d['side'] === 'right') {!! $imageCell !!} @endif
    </tr>
</table>
