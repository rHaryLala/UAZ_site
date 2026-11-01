<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Éditeur visuel · {{ $page->title }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/visual-editor.css') }}?v={{ filemtime(public_path('css/admin/visual-editor.css')) }}">
</head>
<body class="ve-body">
<div class="ve" data-ve data-device="desktop">
    <header class="ve-top">
        <a class="ve-top__back" href="{{ $boot['urls']['classic'] }}" title="Retour au formulaire classique" data-ve-back>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            <span>Quitter</span>
        </a>
        <div class="ve-top__title">
            <strong data-ve-title>{{ $page->title }}</strong>
            <span class="ve-status" data-ve-status role="status" aria-live="polite">Chargement…</span>
        </div>

        <div class="ve-top__group" role="group" aria-label="Taille de l'écran">
            <button type="button" class="ve-icon is-on" data-ve-device="desktop" title="Ordinateur" aria-label="Ordinateur"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></button>
            <button type="button" class="ve-icon" data-ve-device="tablet" title="Tablette" aria-label="Tablette"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/></svg></button>
            <button type="button" class="ve-icon" data-ve-device="mobile" title="Téléphone" aria-label="Téléphone"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/></svg></button>
        </div>

        <div class="ve-top__group" role="group" aria-label="Historique">
            <button type="button" class="ve-icon" data-ve-undo title="Annuler (Ctrl+Z)" aria-label="Annuler" disabled><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg></button>
            <button type="button" class="ve-icon" data-ve-redo title="Rétablir (Ctrl+Y)" aria-label="Rétablir" disabled><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14l5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/></svg></button>
        </div>

        <div class="ve-top__group">
            <button type="button" class="ve-btn" data-ve-discard hidden>Abandonner le brouillon</button>
            <button type="button" class="ve-btn" data-ve-preview>Aperçu</button>
            <button type="button" class="ve-btn ve-btn--primary" data-ve-publish>Publier</button>
        </div>
    </header>

    <aside class="ve-panel" aria-label="Panneau de l'éditeur">
        <div class="ve-tabs" role="tablist">
            <button type="button" role="tab" class="ve-tab is-on" data-ve-tab="add" aria-selected="true">Ajouter</button>
            <button type="button" role="tab" class="ve-tab" data-ve-tab="structure" aria-selected="false">Structure</button>
            <button type="button" role="tab" class="ve-tab" data-ve-tab="settings" aria-selected="false">Réglages</button>
        </div>
        <div class="ve-pane" data-ve-pane="add"></div>
        <div class="ve-pane" data-ve-pane="structure" hidden></div>
        <div class="ve-pane" data-ve-pane="settings" hidden></div>
        <p class="ve-help">Cliquez sur un texte de la page pour le modifier en place. Cliquez sur un bloc pour le sélectionner.</p>
    </aside>

    <main class="ve-stage" data-ve-stage>
        <div class="ve-frame-wrap" data-ve-frame-wrap>
            <iframe class="ve-frame" data-ve-frame src="{{ $boot['urls']['canvas'] }}" title="Page en cours d'édition"></iframe>
        </div>
    </main>

    <div class="ve-toast" data-ve-toast role="status" aria-live="polite" hidden></div>

    <dialog class="ve-media" data-ve-media aria-labelledby="ve-media-title">
        <form method="dialog" class="ve-media__head">
            <h2 id="ve-media-title">Choisir dans la médiathèque</h2>
            <button type="submit" class="ve-icon" aria-label="Fermer">✕</button>
        </form>
        <div class="ve-media__bar">
            <input type="search" placeholder="Rechercher une image…" data-ve-media-search aria-label="Rechercher dans la médiathèque">
            <label class="ve-btn">Envoyer un fichier<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" hidden data-ve-media-file></label>
        </div>
        <form class="ve-media__upload" data-ve-media-upload hidden>
            <p data-ve-media-upload-name></p>
            <label>Texte alternatif (obligatoire) <input type="text" required minlength="3" maxlength="300" data-ve-media-alt placeholder="Décrivez ce que montre l'image"></label>
            <button class="ve-btn ve-btn--primary" type="submit">Envoyer dans la médiathèque</button>
        </form>
        <div class="ve-media__grid" data-ve-media-grid></div>
        <button type="button" class="ve-btn ve-media__more" data-ve-media-more hidden>Charger plus</button>
    </dialog>
</div>

<script id="ve-boot" type="application/json">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('js/admin/visual-editor.js') }}?v={{ filemtime(public_path('js/admin/visual-editor.js')) }}"></script>
</body>
</html>
