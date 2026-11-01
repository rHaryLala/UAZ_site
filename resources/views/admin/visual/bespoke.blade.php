<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Éditeur visuel · {{ $page->title }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin/visual-editor.css') }}?v={{ filemtime(public_path('css/admin/visual-editor.css')) }}">
</head>
<body class="ve-body ve-body--plain">
    <main class="ve-notice">
        <p class="ve-notice__eyebrow">Éditeur visuel</p>
        <h1>« {{ $page->title }} » a une mise en page dessinée sur mesure</h1>
        <p>Cette page n'est pas faite de blocs que l'on peut déplacer : sa mise en page (animations, vidéo, sections) est dessinée page par page, et son texte se trouve dans le code du site. L'éditeur visuel ne peut donc pas la modifier sans risquer de l'abîmer.</p>
        <p>Vous pouvez modifier ici son titre, son résumé, son image et son référencement avec le formulaire classique. Pour changer ses textes ou ses sections, dites-le à l'équipe technique.</p>
        <div class="ve-notice__actions">
            <a class="ve-btn ve-btn--primary" href="{{ $classic }}">Ouvrir le formulaire classique</a>
            <a class="ve-btn" href="{{ $page->url() }}" target="_blank" rel="noopener">Voir la page</a>
        </div>
    </main>
</body>
</html>
