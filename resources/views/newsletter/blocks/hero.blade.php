{{--
    « Photo en fond » : une photo recadrée à la taille exacte (NewsletterRenderer::crop) posée en FOND de cellule, avec du texte par-dessus.
    Outlook (moteur Word) ignore les fonds CSS : il reçoit la photo par VML (commentaires conditionnels « mso »). Une messagerie qui n'affiche aucun fond
    montre la couleur du panneau. « Parallax » = fond fixe (background-attachment: fixed) : pris en charge par certaines messageries seulement (Apple Mail
    sur ordinateur surtout) ; ailleurs la photo reste simplement en place, rien n'est cassé.
--}}
@php
    $asset = $media->get($d['media']);
    $w = max(240, (int) $width);
    $h = (int) $d['height'];
    $layout = $d['layout'];
    $plain = $layout === 'text'; // texte posé directement sur la photo (assombrie)
    $tones = ['navy' => ['#131a3f', '#ffffff', '#e0b957'], 'blue' => ['#232e66', '#ffffff', '#e0b957'], 'gold' => ['#e0b957', '#131a3f', '#131a3f'], 'paper' => ['#fbf9f4', '#131a3f', '#8a724b']];
    [$panelBg, $ink, $accent] = $plain ? ['#131a3f', '#ffffff', '#e0b957'] : ($tones[$d['tone']] ?? $tones['navy']);
    $src = $asset ? \App\Support\NewsletterRenderer::crop($asset, $w, $h, $plain ? 0.4 : 0) : null;
    $baseBg = $src ? $panelBg : ($editing ? '#cfd5ea' : '#232e66');
    $fixed = $d['parallax'] !== '' ? 'background-attachment:fixed;' : '';
    // Panneau : couleur pleine (Outlook) puis version translucide pour les messageries qui la comprennent.
    $fill = 'background:'.$panelBg.';'.($d['opacity'] < 100 ? 'background:'.\App\Support\NewsletterRenderer::rgba($panelBg, (int) $d['opacity']).';' : '');
    $badgeBg = $d['tone'] === 'gold' && ! $plain ? '#131a3f' : '#e0b957';
    $badgeInk = $d['tone'] === 'gold' && ! $plain ? '#ffffff' : '#131a3f';
    $hasBadge = $d['badge'] !== '';
    $showPick = $editing && ! $src; // repère « choisir la photo » sur le plan de travail seulement
    $sidePx = $plain ? 400 : 296;
@endphp
<table role="presentation" class="nl-hero" width="{{ $w }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:{{ $w }}px;">
    <tr>
        <td align="center" valign="top" width="{{ $w }}" height="{{ $h }}" bgcolor="{{ $baseBg }}" @if ($src) background="{{ $src }}" @endif
            style="width:{{ $w }}px;height:{{ $h }}px;background-color:{{ $baseBg }};@if ($src)background-image:url('{{ $src }}');background-size:cover;background-position:center;{{ $fixed }}@endif">
            @if ($src)
                <!--[if gte mso 9]><v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:{{ $w }}px;height:{{ $h }}px;"><v:fill type="frame" src="{{ $src }}" color="{{ $baseBg }}" /><v:textbox style="mso-fit-shape-to-text:false" inset="0,0,0,0"><![endif]-->
            @endif

            @if ($layout === 'left' || $layout === 'right' || $plain)
                {{-- Panneau (ou texte) d'un côté ; de l'autre, en haut : le badge rond et, tant qu'il n'y a pas de photo, le repère pour en choisir une. --}}
                <table role="presentation" width="100%" height="{{ $h }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;height:{{ $h }}px;">
                    <tr>
                        @if ($layout === 'right')
                            <td class="nl-hero-cell" valign="top" align="left" style="vertical-align:top;padding:16px;text-align:left;">@include('newsletter.blocks.hero-side')</td>
                        @endif
                        <td class="nl-hero-cell" width="{{ $sidePx }}" valign="{{ $plain ? 'bottom' : 'middle' }}" style="width:{{ $sidePx }}px;vertical-align:{{ $plain ? 'bottom' : 'middle' }};">
                            @if ($plain)
                                <div style="padding:0 0 36px 36px;text-align:left;">@include('newsletter.blocks.hero-text', ['align' => 'left'])</div>
                            @else
                                <table role="presentation" width="{{ $sidePx }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:{{ $sidePx }}px;">
                                    <tr><td bgcolor="{{ $panelBg }}" align="left" style="{{ $fill }}padding:34px 28px;text-align:left;">@include('newsletter.blocks.hero-text', ['align' => 'left'])</td></tr>
                                </table>
                            @endif
                        </td>
                        @if ($layout !== 'right')
                            <td class="nl-hero-cell" valign="top" align="right" style="vertical-align:top;padding:16px;text-align:right;">@include('newsletter.blocks.hero-side')</td>
                        @endif
                    </tr>
                </table>
            @elseif ($layout === 'center')
                <table role="presentation" width="100%" height="{{ $h }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;height:{{ $h }}px;">
                    <tr>
                        <td align="center" valign="middle" style="vertical-align:middle;padding:0 28px;">
                            @if ($showPick) <div style="margin:0 0 10px;">@include('newsletter.blocks.hero-pick')</div> @endif
                            <table role="presentation" width="420" cellpadding="0" cellspacing="0" border="0" align="center" style="width:100%;max-width:420px;margin:0 auto;">
                                <tr>
                                    <td bgcolor="{{ $panelBg }}" align="center" style="{{ $fill }}padding:34px 30px;text-align:center;">
                                        @if ($hasBadge) <div style="margin:0 0 16px;">@include('newsletter.blocks.hero-badge')</div> @endif
                                        @include('newsletter.blocks.hero-text', ['align' => 'center'])
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @else
                {{-- Bandeau en bas : le texte à gauche, le badge à droite, collé au bas de la photo. --}}
                <table role="presentation" width="100%" height="{{ $h }}" cellpadding="0" cellspacing="0" border="0" style="width:100%;height:{{ $h }}px;">
                    @if ($showPick)
                        <tr><td valign="top" align="left" style="padding:16px;vertical-align:top;">@include('newsletter.blocks.hero-pick')</td></tr>
                    @endif
                    <tr>
                        <td valign="bottom" style="vertical-align:bottom;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                <tr>
                                    <td bgcolor="{{ $panelBg }}" valign="middle" style="{{ $fill }}padding:26px 32px;text-align:left;">@include('newsletter.blocks.hero-text', ['align' => 'left'])</td>
                                    @if ($hasBadge)
                                        <td bgcolor="{{ $panelBg }}" valign="middle" align="right" style="{{ $fill }}padding:16px 28px 16px 0;">@include('newsletter.blocks.hero-badge')</td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endif

            @if ($src)
                <!--[if gte mso 9]></v:textbox></v:rect><![endif]-->
            @endif
        </td>
    </tr>
</table>
