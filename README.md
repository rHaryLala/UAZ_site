# Université Adventiste Zurcher — site web

Nouveau site de l'**UAZ** (Antsirabe, Madagascar), en remplacement du WordPress actuel
(<https://zurcher.edu.mg>). Laravel 13 · Filament 5 · Blade + Alpine.js · Tailwind CSS 4 · GSAP.

> Documentation : [Architecture](docs/ARCHITECTURE.md) · [Décisions](docs/DECISIONS.md) ·
> [Avancement](docs/PROGRESS.md) · [Contenus à fournir](docs/TODO-CONTENU.md) · [Audit WordPress](docs/AUDIT.md)

## Prérequis

| Outil | Version |
| --- | --- |
| PHP | 8.3 ou plus, avec `intl`, `pdo_sqlite`, `pdo_mysql`, `gd` (WebP/AVIF), `zip`, `exif`, `mbstring`, `fileinfo` |
| Composer | 2.x |
| Node.js | 22 ou plus (développement et build uniquement, pas en production) |

> **Laragon (Windows)** : utilisez PHP 8.3 ou 8.4 de Laragon. Le PHP « système » doit aussi avoir les
> extensions ci-dessus activées (`php.ini`).

## Installation

```bash
composer install
cp .env.example .env          # Windows : copy .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev
```

Puis, dans un second terminal : `php artisan serve` (ou le domaine virtuel Laragon `uaz-website.test`).

- Site public : <http://localhost:8000>
- Back-office : <http://localhost:8000/admin>
- Compte de développement : `admin@uaz.test` / mot de passe défini par `SEED_ADMIN_PASSWORD` (`password` si vide
  hors production). **À changer avant toute mise en ligne.**

## Commandes utiles

| Commande | Effet |
| --- | --- |
| `php artisan test` | Tests Pest |
| `vendor/bin/pint` | Formate le PHP |
| `npm run lint` / `npm run format` | ESLint / Prettier |
| `npm run build` | Build Vite de production |
| `npm run e2e` | Test de fumée Playwright (serveur sur `:8000`, données de démonstration) |
| `npm run e2e:menu` / `npm run e2e:shots -- <dossier> /chemin` | Menu plein écran ; captures 390/768/1440 |
| `php artisan redirects:import` | Importe `docs/URL-MAP.csv` (redirections 301) |
| `php artisan demo:purge` | Supprime les contenus de démonstration « (Démo) » |
| `php artisan migrate:fresh --seed` | Réinitialise la base de développement |
| `php artisan import:wordpress --dry-run` | Simule l'import depuis zurcher.edu.mg (phase 5) |
| `php artisan media-library:regenerate` | Régénère les miniatures et conversions WebP/AVIF |

## Import depuis WordPress

```bash
php artisan import:wordpress --dry-run         # simulation : aucune écriture, aucun téléchargement
php artisan import:wordpress                   # import réel + docs/IMPORT-REPORT.md
php artisan import:wordpress --only=posts,team # étapes : users,pages,posts,team,testimonials,redirects,media
php artisan media-library:regenerate           # produit ensuite les variantes AVIF
```

Lecture seule du site existant (GET uniquement, ≥ 600 ms entre requêtes, robots.txt respecté), **idempotent**, et
sans jamais écraser un contenu retouché dans le back-office (sauf `--force`). Détails : `docs/DECISIONS.md` D-023 à D-027.

## Base de données

SQLite en développement, MySQL/MariaDB en production : le code n'utilise que des fonctionnalités
communes. Pour MySQL, renseignez `DB_CONNECTION=mysql` et les variables `DB_*` dans `.env`.

## Rôles du back-office

| Rôle | Droits |
| --- | --- |
| `super-admin` | Tout, y compris utilisateurs et rôles |
| `editor` | Contenus (publier, modifier, supprimer), médiathèque, réglages, messages de contact |
| `contributor` | Crée et modifie **ses propres brouillons** ; ne publie pas, ne supprime pas |

## Licence

Code propriétaire de l'Université Adventiste Zurcher, sauf mention contraire des dépendances.
