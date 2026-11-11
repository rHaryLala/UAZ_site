<div style="border-left:4px solid {{ $d['accent'] }};padding:4px 0 4px 18px;">
    <div style="font-family:{{ $headingFont }};font-size:22px;line-height:1.4;font-style:italic;color:{{ $theme['accent'] }};" {!! $ed('text') !!}>{{ $d['text'] }}</div>
    @if ($d['author'] !== '' || $editing)
        <div style="margin-top:10px;font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:#6b7184;" {!! $ed('author') !!}>{{ $d['author'] }}</div>
    @endif
</div>
