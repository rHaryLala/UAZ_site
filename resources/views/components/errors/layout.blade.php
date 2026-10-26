@props(['code', 'title', 'message'])

{{-- Page d'erreur soignée : fonctionne même si la base de données est indisponible (aucune requête, pas de composer de vue). --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — Université Adventiste Zurcher</title>
    @vite(['resources/css/app.css'])
</head>
<body class="on-dark relative isolate flex min-h-screen items-center overflow-hidden bg-navy-900 text-white">
    <x-madagascar class="pointer-events-none absolute top-1/2 -right-10 -z-10 h-[140%] -translate-y-1/2 text-navy-800" />
    <x-triangle class="pointer-events-none absolute -bottom-1/3 -left-[10%] -z-10 w-[60rem] text-gold-300/10" />

    <main class="container-site py-16">
        <a href="/" class="mb-14 inline-flex items-center gap-3" aria-label="Université Adventiste Zurcher — accueil">
            <x-seal :size="64" eager class="size-14" />
            <span class="font-serif text-2xl tracking-[0.08em]">ZURCHER</span>
        </a>
        <p class="eyebrow mb-4">Erreur {{ $code }}</p>
        <h1 class="max-w-3xl font-serif text-display text-white">{{ $title }}</h1>
        <p class="mt-6 max-w-xl text-lead text-navy-100">{{ $message }}</p>
        <div class="mt-10 flex flex-wrap gap-4">
            <a href="/" class="btn btn-gold">Retour à l'accueil</a>
            <a href="/recherche" class="btn btn-outline">Rechercher sur le site</a>
        </div>
    </main>
</body>
</html>
