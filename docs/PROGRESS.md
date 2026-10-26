# Avancement

| Phase | État | Commit |
| --- | --- | --- |
| 0 · Audit du site existant | ✅ Terminée | `docs: audit du site WordPress existant` |
| 1 · Fondations | ✅ Terminée | `feat: fondations Laravel, Filament, rôles et médias` |
| 2 · Modèles et back-office | ✅ Terminée | `feat: modèles, ressources Filament et médiathèque` |
| 3 · Direction artistique | ✅ Terminée | `feat: design system aux couleurs du sceau` |
| 4 · Pages publiques et animations | ✅ Terminée | `feat: pages publiques et animations` |
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
