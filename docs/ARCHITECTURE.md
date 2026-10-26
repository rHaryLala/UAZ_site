# Architecture

## Vue d'ensemble

```
Navigateur ── Nginx/Caddy ── PHP-FPM ── Laravel 13
                                         ├─ Site public : routes web → contrôleurs → vues Blade (+ Alpine, Livewire ciblé)
                                         ├─ Back-office : Filament 5 sur /admin
                                         ├─ Médias : spatie/laravel-medialibrary (disque `public`)
                                         ├─ Rôles : spatie/laravel-permission
                                         └─ Base : SQLite (dev) / MySQL-MariaDB (prod)
Build front : Vite + Tailwind 4 → public/build (Node inutile en production)
```

## Principes

1. **Contenu dans le HTML.** Le site public est rendu côté serveur (Blade). JavaScript ne fait qu'embellir :
   la page reste lisible sans JS et indexable.
2. **Livewire seulement là où c'est utile** : formulaire de contact et recherche. Le reste est Blade + Alpine.
   Alpine est fourni par Livewire (une seule instance) ; les plugins Alpine s'enregistrent via `window.Alpine`.
3. **Animations par modules** (`resources/js/animations/*.js`), chargés à la demande (`import()`), toujours
   protégés par `gsap.matchMedia()` pour `prefers-reduced-motion`.
4. **Un seul disque de médias** (`public`) avec conversions `thumb`, `card`, `hero` en WebP (+ AVIF si GD le
   permet) et images responsives.
5. **Autorisation par permissions** (Spatie) : le super-admin passe `Gate::before`; éditeur et contributeur
   reçoivent des permissions de la matrice `RolesAndPermissionsSeeder::MATRIX`.

## Arborescence

```
app/
  Models/                Modèles Eloquent (+ Concerns/HasUazMedia : conversions d'images communes)
  Filament/              Ressources et pages du back-office (découvertes automatiquement)
  Providers/Filament/    AdminPanelProvider (panneau /admin, 2FA, groupes de navigation)
config/uaz.php           Réglages propres au projet (langues, médias, contact, import)
database/seeders/        Rôles, super-admin de dev, données de démonstration
resources/css/           Tailwind + tokens de marque
resources/js/            app.js + animations/*
docs/                    Documentation, audit, plan d'import
tests/                   Pest (Feature, Unit) + e2e Playwright
```

## Données (phase 2)

Page (blocs), Actualité, Événement, Formation, Faculté, Membre, Témoignage, Partenaire, Album, Réglages du
site, Menu, Message de contact, Redirection. Les statuts brouillon/publié + date de publication planifiée
sont portés par un scope commun `published()`.

## Sécurité

- 2FA TOTP optionnelle pour tout compte du back-office (Filament, colonnes `app_authentication_*`).
- Uploads limités par type MIME et taille (`config/uaz.php`), originaux redimensionnés à 2560 px.
- Contenu riche nettoyé à l'import et échappé à l'affichage ; en-têtes de sécurité et CSP en phase 6.

## Internationalisation (préparation)

- `config/uaz.php` déclare `locales` (aujourd'hui `['fr']`) et `default_locale`.
- Toutes les chaînes d'interface passent par `__()` / fichiers `lang/fr`; l'ajout de `lang/en` suffira.
- Pour les contenus, un champ traduisible (JSON par langue) sera introduit en ajoutant `spatie/laravel-translatable`
  aux modèles concernés : les colonnes texte sont déjà isolées (pas de logique métier dedans).
- Les routes publiques passeront sous un préfixe `/{locale}` uniquement pour les langues autres que la
  langue par défaut, afin de ne pas casser les URLs françaises.
