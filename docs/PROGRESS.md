# Avancement

| Phase | État | Commit |
| --- | --- | --- |
| 0 · Audit du site existant | ✅ Terminée | `docs: audit du site WordPress existant` |
| 1 · Fondations | ✅ Terminée | `feat: fondations Laravel, Filament, rôles et médias` |
| 2 · Modèles et back-office | ⏳ À faire | |
| 3 · Direction artistique | ⏳ À faire | |
| 4 · Pages publiques et animations | ⏳ À faire | |
| 5 · Import WordPress | ⏳ À faire | |
| 6 · Qualité, SEO, a11y, sécurité | ⏳ À faire | |
| 7 · Déploiement et passation | ⏳ À faire | |

## Phase 0 — notes

- Accès au site existant : **OK** (API REST ouverte, robots.txt respecté, 600 ms entre requêtes).
- Résultats : 23 pages, 18 articles, 529 médias (~979 Mo), 3 auteurs, 1 catégorie.
- Livrables : `docs/AUDIT.md`, `docs/URL-MAP.csv` (45 redirections), `docs/DECISIONS.md`,
  `docs/TODO-CONTENU.md`.
- Environnement : PHP 8.5.5, Composer 2.9.7, Node 24.14, npm 11.9. Python absent (scripts en Node/PHP).
- Le logo `docs/assets/UAZLogo.png` a été copié depuis la pièce jointe de la conversation.

## Phase 1 — notes

- Laravel 13.38, Filament 5.10 (panneau `/admin`), Pest 4 (9 tests verts), Pint, ESLint 10, Prettier 3.
- Rôles `super-admin`, `editor`, `contributor` seedés ; super-admin de dev `admin@uaz.test`.
- Media Library : disque `public`, trait `HasUazMedia` (conversions `thumb`/`card`/`hero` en WebP + AVIF), nettoyage planifié des orphelins.
- 2FA TOTP optionnelle (colonnes `app_authentication_*`).
- À faire plus tard : compression à l'envoi dans l'interface Filament (phase 2), polices Filament auto-hébergées.
