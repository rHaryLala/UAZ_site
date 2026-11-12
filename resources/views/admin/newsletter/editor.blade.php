<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Newsletter · {{ $newsletter->subject }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/visual-editor.css') }}?v={{ filemtime(public_path('css/admin/visual-editor.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin/newsletter-editor.css') }}?v={{ filemtime(public_path('css/admin/newsletter-editor.css')) }}">
</head>
<body class="ve-body nl-body">
<div class="ve nl" data-nl data-device="desktop">
    <header class="ve-top">
        <a class="ve-top__back" href="{{ $boot['urls']['list'] }}" title="Retour à la liste des newsletters" data-nl-back>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            <span>Quitter</span>
        </a>

        <div class="ve-top__title nl-subject">
            <label class="sr-only" for="nl-subject">Objet du courrier</label>
            <input id="nl-subject" type="text" maxlength="190" placeholder="Objet du courrier" data-nl-subject value="{{ $newsletter->subject }}" @disabled($boot['locked'])>
            <span class="ve-status" data-nl-status role="status" aria-live="polite">{{ $boot['locked'] ? 'Newsletter envoyée' : 'Chargement…' }}</span>
        </div>

        <div class="ve-top__group" role="group" aria-label="Taille de l'écran">
            <button type="button" class="ve-icon is-on" data-nl-device="desktop" title="Ordinateur" aria-label="Ordinateur"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></button>
            <button type="button" class="ve-icon" data-nl-device="mobile" title="Téléphone" aria-label="Téléphone"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/></svg></button>
        </div>

        @unless ($boot['locked'])
            <div class="ve-top__group" role="group" aria-label="Historique">
                <button type="button" class="ve-icon" data-nl-undo title="Annuler (Ctrl+Z)" aria-label="Annuler" disabled><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg></button>
                <button type="button" class="ve-icon" data-nl-redo title="Rétablir (Ctrl+Y)" aria-label="Rétablir" disabled><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/></svg></button>
            </div>

            <div class="ve-top__group">
                <button type="button" class="ve-btn" data-nl-open-test>Envoyer un test</button>
                <button type="button" class="ve-btn ve-btn--primary" data-nl-open-send>Envoyer…</button>
            </div>
        @endunless
    </header>

    <aside class="ve-panel nl-panel" aria-label="Panneau de l'éditeur">
        <div class="nl-resizer" role="separator" aria-orientation="vertical" aria-label="Largeur du panneau : glissez, ou utilisez les flèches gauche et droite (double-clic : taille d'origine)" tabindex="0" data-nl-resizer></div>
        @if ($boot['locked'])
            <div class="ve-pane">
                <div class="ve-notice">
                    <p class="ve-notice__eyebrow">{{ $boot['status'] === 'sending' ? 'Envoi en cours' : 'Newsletter envoyée' }}</p>
                    <p>
                        @if ($boot['status'] === 'sending')
                            Cette newsletter part à {{ $boot['recipients'] }} abonné(s). Elle ne peut plus être modifiée.
                        @else
                            Envoyée le {{ $boot['sentAt'] }} à {{ $boot['recipients'] }} abonné(s). Elle ne peut plus être modifiée : pour une nouvelle version, dupliquez-la depuis la liste.
                        @endif
                    </p>
                </div>
            </div>
        @else
            <div class="ve-tabs" role="tablist">
                <button type="button" role="tab" class="ve-tab is-on" data-nl-tab="add" aria-selected="true">Ajouter</button>
                <button type="button" role="tab" class="ve-tab" data-nl-tab="structure" aria-selected="false">Structure</button>
                <button type="button" role="tab" class="ve-tab" data-nl-tab="block" aria-selected="false">Bloc</button>
                <button type="button" role="tab" class="ve-tab" data-nl-tab="letter" aria-selected="false">Newsletter</button>
            </div>
            <div class="ve-pane" data-nl-pane="add"></div>
            <div class="ve-pane" data-nl-pane="structure" hidden></div>
            <div class="ve-pane" data-nl-pane="block" hidden></div>
            <div class="ve-pane" data-nl-pane="letter" hidden></div>
            <p class="ve-help">Cliquez sur un texte du plan de travail pour le modifier. Cliquez sur un bloc pour le sélectionner, le déplacer ou le régler.</p>
        @endif
    </aside>

    <main class="ve-stage nl-stage" data-nl-stage>
        <div class="ve-frame-wrap" data-nl-frame-wrap>
            <iframe class="ve-frame" data-nl-frame title="Plan de travail : la newsletter telle qu'elle sera reçue"></iframe>
        </div>
    </main>

    <div class="ve-toast" data-nl-toast role="status" aria-live="polite" hidden></div>

    {{-- Envoi d'essai --}}
    <dialog class="nl-dialog" data-nl-dialog="test" aria-labelledby="nl-test-title">
        <form method="dialog" class="nl-dialog__form" data-nl-test-form>
            <h2 id="nl-test-title">Envoyer un test</h2>
            <p>Le courrier est envoyé à cette adresse seulement, tel qu'il est sur le plan de travail. Aucun abonné n'est concerné.</p>
            <label class="ve-label" for="nl-test-to">Adresse e-mail</label>
            <input id="nl-test-to" type="email" required maxlength="190" value="{{ $boot['email'] }}" data-nl-test-to>
            <p class="nl-dialog__error" data-nl-test-error role="alert" hidden></p>
            <div class="nl-dialog__actions">
                <button type="button" class="ve-btn" data-nl-close>Annuler</button>
                <button type="submit" class="ve-btn ve-btn--primary" data-nl-test-submit>Envoyer l'essai</button>
            </div>
        </form>
    </dialog>

    {{-- Envoi à tous les abonnés --}}
    <dialog class="nl-dialog" data-nl-dialog="send" aria-labelledby="nl-send-title">
        <form method="dialog" class="nl-dialog__form" data-nl-send-form>
            <h2 id="nl-send-title">Envoyer à tous les abonnés ?</h2>
            <p data-nl-send-text></p>
            <ul class="nl-dialog__checks" data-nl-send-warn hidden></ul>
            <p class="nl-dialog__warn">L'envoi ne peut pas être annulé, et la newsletter ne sera plus modifiable.</p>
            <label class="nl-check"><input type="checkbox" required data-nl-send-confirm> J'ai relu la newsletter et fait un envoi d'essai si nécessaire.</label>
            <p class="nl-dialog__error" data-nl-send-error role="alert" hidden></p>
            <div class="nl-dialog__actions">
                <button type="button" class="ve-btn" data-nl-close>Annuler</button>
                <button type="submit" class="ve-btn ve-btn--primary" data-nl-send-submit>Envoyer maintenant</button>
            </div>
        </form>
    </dialog>

    {{-- Modèles de newsletter --}}
    <dialog class="nl-dialog nl-dialog--wide" data-nl-dialog="templates" aria-labelledby="nl-tpl-title">
        <div class="nl-dialog__form">
            <div class="nl-dialog__head">
                <h2 id="nl-tpl-title">Choisir un modèle</h2>
                <button type="button" class="ve-icon" data-nl-close aria-label="Fermer">✕</button>
            </div>
            <p>Le modèle remplace la mise en page de la newsletter ; vous pourrez tout modifier ensuite (et revenir en arrière avec Ctrl+Z).</p>
            <div class="nl-tpl-grid" data-nl-templates-grid></div>
        </div>
    </dialog>

    {{-- Médiathèque (choix d'une image) --}}
    <dialog class="ve-media" data-nl-media aria-labelledby="nl-media-title">
        <form method="dialog" class="ve-media__head">
            <h2 id="nl-media-title">Choisir dans la médiathèque</h2>
            <button type="submit" class="ve-icon" aria-label="Fermer">✕</button>
        </form>
        <div class="ve-media__bar">
            <input type="search" placeholder="Rechercher une image…" data-nl-media-search aria-label="Rechercher dans la médiathèque">
            <label class="ve-btn">Envoyer un fichier<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-nl-media-file></label>
        </div>
        <form class="ve-media__upload" data-nl-media-upload hidden>
            <p data-nl-media-upload-name></p>
            <label>Texte alternatif (obligatoire) <input type="text" required minlength="3" maxlength="300" data-nl-media-alt placeholder="Décrivez ce que montre l'image"></label>
            <button class="ve-btn ve-btn--primary" type="submit">Envoyer dans la médiathèque</button>
        </form>
        <div class="ve-media__grid" data-nl-media-grid></div>
        <button type="button" class="ve-btn ve-media__more" data-nl-media-more hidden>Charger plus</button>
    </dialog>
</div>

<script id="nl-boot" type="application/json">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('js/admin/newsletter-editor.js') }}?v={{ filemtime(public_path('js/admin/newsletter-editor.js')) }}"></script>
</body>
</html>
