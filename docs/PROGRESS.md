# Avancement

| Phase | État | Commit |
| --- | --- | --- |
| 0 · Audit du site existant | ✅ Terminée | `docs: audit du site WordPress existant` |
| 1 · Fondations | ✅ Terminée | `feat: fondations Laravel, Filament, rôles et médias` |
| 2 · Modèles et back-office | ✅ Terminée | `feat: modèles, ressources Filament et médiathèque` |
| 3 · Direction artistique | ✅ Terminée | `feat: design system aux couleurs du sceau` |
| 4 · Pages publiques et animations | ✅ Terminée | `feat: pages publiques et animations` |
| 5 · Import WordPress | ✅ Terminée | `feat: import WordPress idempotent` |
| 6 · Qualité, SEO, a11y, sécurité | ✅ Terminée | `feat: SEO, sécurité et accessibilité` |
| 7 · Déploiement et passation | ✅ Terminée | `docs: déploiement, sauvegardes et guide du rédacteur` |

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

## Phase 2 — notes

- 15 modèles + migrations + factories ; 16 ressources/pages Filament en français (Contenus, Formations, Communauté, Médias, Site, Administration).
- Médiathèque (`MediaAsset`) : sélecteur avec recherche et miniatures, envoi multiple avec texte alternatif obligatoire, compression à 2560 px, conversions WebP (+ AVIF).
- Pages en blocs flexibles (texte, image, galerie, citation, appel à l'action, chiffres clés, cartes), SEO, brouillon/publié/programmé.
- Redirections 301 : modèle + middleware global mis en cache + `php artisan redirects:import` (41 lignes depuis URL-MAP.csv).
- Politiques par rôle ; contributeur limité à ses brouillons (garde-fou aussi côté modèle).
- Seeders : structure réelle (réglages, 7 licences, pages système, menus) + démo locale (`demo:purge`).
- **74+ tests Pest verts** ; Pint propre.
- Maquette de l'utilisateur intégrée : voir `docs/MAQUETTE.md`.
- Reste à vérifier visuellement dans le navigateur (Playwright, phase 4) : mise en page des formulaires Filament.

## Phase 3 — notes

- Couleurs extraites du sceau par script : marine #232E66, or #8A724B, noir #060807 ; échelles OKLCH ; contrastes AA vérifiés et testés.
- Silhouette de Madagascar vectorisée depuis le logo (resources/svg/madagascar.svg) ; triangle en SVG ; sceau optimisé (WebP 96–768 px).
- Fraunces + Inter auto-hébergées (latin), préchargées.
- 20 composants Blade, layout, en-tête + menu plein écran (CSS), pied de page, page /design-system.
- Vérifié visuellement (Playwright 390/768/1440) ; menu plein écran : 18/18 contrôles (focus piégé, Échap, ARIA).
- JS : entrée 1 Ko, Alpine CSP, Livewire à la demande, GSAP/Lenis différés.

## Phase 4 — notes

- Pages : accueil (13 sections), formations (filtrables) + détail, actualités + détail, événements, galerie + visionneuse, équipe, contact (Livewire), recherche (Livewire), pages libres par chemin, 403/404/419/429/500/503 soignées.
- Animations GSAP : héros (SplitText + triangle qui se dessine + parallaxe), compteurs, piliers au scroll, apparitions ; Lenis ; mouvement réduit et sans JS vérifiés.
- **128 tests Pest** + **23 contrôles Playwright** réels (tests/e2e/smoke.mjs) + menu plein écran (18 contrôles).
- Vérifié visuellement à 390/768/1440 px.
- Poids JS (gzip) : entrée 1 Ko ; Alpine 23 Ko ; GSAP 47 Ko et Lenis 6 Ko chargés après le rendu ; Livewire +99 Ko seulement sur /contact et /recherche.
- Limites connues : photos réelles absentes (visuels de substitution aux couleurs du sceau) ; pages légales (confidentialité, accessibilité) à créer ; liens de la maquette sans page non implémentés.

## Phase 5 — notes

- `php artisan import:wordpress` (`--dry-run`, `--only`, `--force`) : lecture seule de l'API (GET espacés de 600 ms, robots.txt), idempotent, ne réécrit jamais un contenu retouché dans le back-office.
- **Import réel exécuté le 26-27 octobre 2026** : 3 auteurs, 12 pages mappées sur les pages système, 18 articles (dates d'origine), 92 membres de l'équipe, 3 témoignages vidéo (désactivés), ≈ 145 médias, 41 redirections. **0 échec.** Voir `docs/IMPORT-REPORT.md`.
- Défauts trouvés grâce à l'import réel et corrigés : émojis Facebook hotlinkés, légendes de galerie Divi devenues des titres, sauts de niveau de titres, pages d'images-liens vers des PDF.

## Phase 6 — notes

- Lighthouse mobile (réseau lent émulé, serveur en mode production) : **performance 92–98, accessibilité 100, bonnes pratiques 100, SEO 100** (docs/QUALITE.md). axe-core : **0 violation** sur 13 pages × 2 largeurs.
- 190 tests Pest ; 24 contrôles Playwright (smoke), menu plein écran (18), parcours rédacteur complet (9).
- Défauts réels trouvés en navigateur et corrigés : Livewire non chargé dans le back-office (`inject_assets`), CSP bloquant les médias servis depuis `APP_URL`, miniatures du sélecteur d'images en taille réelle, `#[Locked]` sans import, suppression possible de son propre compte super-admin, script de sauvegarde interrompu par `set -e`.

## Phase 7 — notes

- `docs/DEPLOIEMENT.md` (VPS Ubuntu, Nginx/Caddy, PHP-FPM, MariaDB, HTTPS, queue, cron, sauvegardes, retour arrière), `docs/PLAN-DE-BASCULE.md`, `docs/GUIDE-REDACTEUR.md` (captures dans `docs/screenshots/`), `docs/QUALITE.md`.
- `scripts/backup.sh` (testé), `restore.sh`, `deploy.sh` ; `Dockerfile` et `docker-compose.yml` optionnels : **syntaxe validée (`docker compose config`) mais image non construite** (démon Docker arrêté sur la machine de développement).
