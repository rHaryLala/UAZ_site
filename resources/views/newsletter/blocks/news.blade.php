@php($posts = \App\Support\NewsletterRenderer::newsPosts((int) $d['count']))
<div style="margin:0 0 14px;font-family:{{ $headingFont }};font-weight:700;font-size:24px;line-height:1.2;color:{{ $theme['accent'] }};" {!! $ed('title') !!}>{{ $d['title'] }}</div>
@forelse ($posts as $post)
    @php($cover = \App\Support\NewsletterRenderer::imageUrl($post->cover))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;border-top:1px solid #e4e6ee;">
        <tr>
            @if ($cover)
                <td class="nl-col" width="32%" valign="top" style="width:32%;padding:14px 18px 0 0;"><img src="{{ $cover }}" alt="" style="display:block;width:100%;height:auto;border-radius:6px;"></td>
            @endif
            <td class="nl-col" valign="top" style="padding:14px 0 0;">
                <a href="{{ $post->url() }}" style="font-family:{{ $headingFont }};font-size:18px;line-height:1.3;font-weight:700;color:{{ $theme['text'] }};text-decoration:none;">{{ $post->title }}</a>
                <div style="margin-top:4px;font-size:12px;color:#6b7184;">{{ $post->published_at?->translatedFormat('j F Y') }}</div>
                @if ($d['excerpt'] && $post->excerpt)
                    <div style="margin-top:6px;font-size:14px;line-height:1.55;color:{{ $theme['text'] }};">{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 150) }}</div>
                @endif
            </td>
        </tr>
    </table>
@empty
    @if ($editing)
        <div style="padding:18px;border:2px dashed #c4c9d8;border-radius:8px;text-align:center;color:#6b7184;font-size:14px;">Aucune actualité publiée pour le moment : les articles apparaîtront ici.</div>
    @endif
@endforelse
