@php($tag = in_array($d['level'], ['h1', 'h2', 'h3'], true) ? $d['level'] : 'h2')
<{{ $tag }} style="margin:0;font-family:{{ $headingFont }};font-weight:700;font-size:{{ $d['size'] }}px;line-height:1.2;color:{{ $d['color'] ?: $theme['accent'] }};text-align:{{ $d['align'] }};" {!! $ed('text') !!}>{{ $d['text'] }}</{{ $tag }}>
