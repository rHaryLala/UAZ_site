# Décisions

Format : **décision** — alternatives — raison.

## D-001 · Structure des nouvelles URLs (Phase 0)
- **Décision** : `/formations`, `/admissions` (+ `/admissions/licence|master|frais-academiques|telechargements`),
  `/vie-etudiante` (+ sous-pages), `/actualites/{slug}`, `/equipe`, `/contact`, `/recherche`.
- Alternatives : conserver les slugs WordPress tels quels ; arborescence plate.
- Raison : URLs parlantes et hiérarchiques pour le SEO ; chaque ancienne URL est couverte par une 301
  (`docs/URL-MAP.csv`).

## D-002 · Slugs des articles conservés
- **Décision** : on garde le slug WordPress, sauf les 3 slugs défectueux (régénérés depuis le titre).
- Raison : limite le nombre de redirections « différentes » et préserve le référencement existant.

## D-003 · Pages obsolètes non migrées
- **Décision** : `aumonerie`, `cours-a-la-carte` (cours de févr. 2026), `s-a`, `home`, `accueil-2` ne sont pas
  importées ; elles redirigent (voir AUDIT §7).
- Alternative : tout importer puis dépublier. Rejetée : pollue le back-office.

## D-004 · Version de PHP
- PHP 8.5.5 est installé en local ; le projet exige `^8.3` dans `composer.json` pour rester déployable
  sur un VPS standard.

## D-005 · Collecte des données
- L'API REST étant ouverte, aucun crawl HTML n'est nécessaire pour les contenus ; le HTML de l'accueil
  et de la page contact a été lu une fois pour le menu, les réseaux sociaux et les formulaires.

## D-006 · Pas de trailer d'attribution dans les commits
- **Décision** : les commits ne contiennent aucun `Co-Authored-By` ni mention « Generated with Claude Code ».
- Raison : préférence explicite et permanente de l'utilisateur (`~/.claude/CLAUDE.md`), prioritaire sur les
  réglages de session par défaut.

## D-007 · Versions retenues (Phase 1)
- **Décision** : Laravel 13.38, Filament 5.10 (Livewire 4), spatie/laravel-medialibrary 11, laravel-permission 8,
  laravel-sluggable 4, Pest 4, Tailwind 4, Vite 8. Ce sont les dernières versions stables résolues par Composer/npm.
- Le PHP « système » de la machine (8.5.5) n'a pas `intl`/`pdo_sqlite`/`pdo_mysql` activés. On utilise le PHP 8.3.30
  de Laragon, qui les a, sans modifier la configuration globale. `composer.json` exige `^8.3`.

## D-008 · Fuseau horaire et langue
- **Décision** : `APP_TIMEZONE=Indian/Antananarivo` (UTC+3, sans heure d'été), `APP_LOCALE=fr`.
- Alternative : UTC en base + conversion à l'affichage. Écartée : plus de pièges pour des rédacteurs non techniques
  (publication planifiée à l'heure locale) sans bénéfice ici (un seul fuseau).

## D-009 · File d'attente en développement
- **Décision** : `QUEUE_CONNECTION=sync` dans `.env.example` (conversions d'images immédiates, aucun worker à lancer).
  En production : `database` + `queue:work` supervisé (voir DEPLOIEMENT).

## D-010 · Autorisations par permissions Spatie, pas de Filament Shield
- **Décision** : matrice courte (`content.*`, `media.manage`, `settings.manage`, `messages.*`, `users.manage`)
  portée par `RolesAndPermissionsSeeder`, appliquée par des Policies.
- Alternative : Filament Shield (une permission par ressource et action). Écartée : trop granulaire pour trois rôles.

## D-011 · 2FA
- **Décision** : 2FA TOTP native de Filament (`AppAuthentication`), optionnelle, activée par chaque utilisateur
  depuis son profil. Rendre obligatoire = `->isRequired()` sur le fournisseur (à décider avec l'équipe).

## D-012 · Fichiers d'instructions du squelette Laravel
- Le squelette `laravel/laravel` 13 fournit `AGENTS.md` et `CLAUDE.md` génériques (installation d'outils
  tiers). Ils ont été supprimés : ils ne reflètent pas ce projet.
