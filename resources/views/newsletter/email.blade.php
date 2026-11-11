@php
    // Repères de modification sur le plan de travail : seulement dans l'éditeur ($editing), jamais dans le courrier envoyé.
    $ed = fn (string $field): string => $editing ? 'data-nl-field="'.$field.'"' : '';
    $bodyFont = "'Helvetica Neue', Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; background: {{ $theme['background'] }}; }
        img { border: 0; outline: none; text-decoration: none; max-width: 100%; height: auto; }
        a { color: {{ $theme['accent'] }}; }
        .nl-text p { margin: 0 0 14px; }
        .nl-text p:last-child { margin-bottom: 0; }
        .nl-text ul, .nl-text ol { margin: 0 0 14px; padding-left: 22px; }
        .nl-text blockquote { margin: 0 0 14px; padding-left: 14px; border-left: 3px solid {{ $theme['gold'] }}; }
        .nl-text h2, .nl-text h3 { margin: 0 0 10px; font-family: {{ $headingFont }}; color: {{ $theme['accent'] }}; }
        @media only screen and (max-width: 520px) {
            .nl-outer { padding: 0 !important; }
            .nl-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .nl-col { display: block !important; width: 100% !important; padding: 0 0 16px 0 !important; }
        }
    </style>
    @if ($editing)
        <link rel="stylesheet" href="{{ asset('css/admin/newsletter-canvas.css') }}?v={{ filemtime(public_path('css/admin/newsletter-canvas.css')) }}">
    @endif
</head>
<body style="margin:0;padding:0;background:{{ $theme['background'] }};font-family:{{ $bodyFont }};color:{{ $theme['text'] }};">
    {{-- Texte d'aperçu affiché dans la liste des messages, invisible dans le courrier. --}}
    @if ($preheader !== '')
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $theme['background'] }};">
        <tr>
            <td align="center" class="nl-outer" style="padding:24px 12px;">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:640px;background:{{ $theme['card'] }};">
                    @if ($theme['header'])
                        <tr>
                            <td class="nl-pad" style="background:{{ $theme['header_color'] }};padding:22px 32px;" {!! $editing ? 'data-nl-lock="header"' : '' !!}>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right:14px;"><img src="{{ asset('icon-192.png') }}" width="44" height="44" alt="" style="display:block;border-radius:50%;"></td>
                                        <td style="font-family:{{ $headingFont }};color:#ffffff;font-size:17px;line-height:1.25;letter-spacing:.02em;">
                                            Université Adventiste<br><span style="color:#e0b957;">Zurcher</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr><td style="height:3px;line-height:3px;font-size:3px;background:{{ $theme['gold'] }};">&nbsp;</td></tr>
                    @endif

                    <tr><td style="height:10px;line-height:10px;font-size:10px;">&nbsp;</td></tr>
                    @foreach ($blocks as $i => $block)
                        @php($d = $block['data'])
                        <tr>
                            <td class="nl-pad {{ $editing && $selected === $i ? 'nl-selected' : '' }}"
                                @if ($editing) data-nl-block="{{ $i }}" data-nl-type="{{ $block['type'] }}" data-nl-id="{{ $block['id'] }}" @endif
                                style="padding:14px 32px;">
                                @include('newsletter.blocks.'.$block['type'], ['d' => $d, 'i' => $i])
                            </td>
                        </tr>
                    @endforeach

                    @if ($editing && $blocks === [])
                        <tr><td class="nl-pad" data-nl-empty style="padding:60px 32px;text-align:center;color:#9aa1b5;font-size:15px;">La lettre est vide. Ajoutez un premier bloc depuis le panneau de gauche.</td></tr>
                    @endif

                    {{-- Pied de page : toujours présent (adresse et lien de désinscription obligatoires), non modifiable. --}}
                    <tr>
                        <td class="nl-pad" style="padding:28px 32px 30px;border-top:1px solid #e4e6ee;font-size:12px;line-height:1.6;color:#6b7184;text-align:center;" {!! $editing ? 'data-nl-lock="footer"' : '' !!}>
                            <strong style="color:{{ $theme['text'] }};">Université Adventiste Zurcher</strong><br>
                            PK 135, Route Nationale 7, Antsirabe, Madagascar<br>
                            <a href="{{ url('/') }}" style="color:{{ $theme['accent'] }};">{{ preg_replace('#^https?://#', '', url('/')) }}</a>
                            <br><br>
                            Vous recevez ce message car vous êtes abonné(e) à la lettre d'information de l'UAZ.<br>
                            @if ($unsubscribe)
                                <a href="{{ $unsubscribe }}" style="color:#6b7184;">Se désinscrire</a>
                            @else
                                <span style="text-decoration:underline;">Se désinscrire</span> <em>(le lien personnel est ajouté à chaque envoi)</em>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($editing)
        <script src="{{ asset('js/admin/newsletter-canvas.js') }}?v={{ filemtime(public_path('js/admin/newsletter-canvas.js')) }}"></script>
    @endif
</body>
</html>
