@php($asset = $media->get($d['media']))
@php($src = \App\Support\NewsletterRenderer::imageUrl($asset))
@if ($src)
    @php($img = '<img src="'.e($src).'" alt="'.e((string) $asset->alt).'" style="display:block;width:'.$d['width'].'%;max-width:100%;height:auto;border-radius:'.$d['radius'].'px;'.($d['align'] === 'center' ? 'margin:0 auto;' : ($d['align'] === 'right' ? 'margin:0 0 0 auto;' : '')).'">')
    @if ($d['link'] !== '')
        <a href="{{ $d['link'] }}" style="text-decoration:none;">{!! $img !!}</a>
    @else
        {!! $img !!}
    @endif
@elseif ($editing)
    <div data-nl-placeholder style="padding:46px 16px;border:2px dashed #c4c9d8;border-radius:8px;text-align:center;color:#6b7184;font-size:14px;">Image : choisissez-la dans le panneau de droite</div>
@endif
