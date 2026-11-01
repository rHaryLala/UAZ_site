<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Page en cours d'édition</title>
    {{-- Seule la feuille de style du site : pas de script public, donc aucune animation pendant l'édition. --}}
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="{{ asset('css/admin/visual-canvas.css') }}?v={{ filemtime(public_path('css/admin/visual-canvas.css')) }}">
</head>
<body class="ve-canvas antialiased">
    <main id="ve-root"></main>
    <script src="{{ asset('js/admin/visual-canvas.js') }}?v={{ filemtime(public_path('js/admin/visual-canvas.js')) }}"></script>
</body>
</html>
