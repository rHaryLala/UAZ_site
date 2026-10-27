# Qualité : performance, accessibilité, SEO, sécurité

Mesures réalisées le 27 octobre 2026 sur le site avec le contenu importé de zurcher.edu.mg.

## Méthode

- **Lighthouse 12, profil mobile** (émulation d'un téléphone, réseau « 4G lent », processeur ralenti 4×).
- Serveur proche de la production : PHP en `APP_ENV=production`, `APP_DEBUG=false`, 4 workers, derrière un relais qui compresse
  (Brotli/gzip) et met en cache les assets comme Nginx (`tests/e2e/prod-like-server.mjs`).
  Les temps de réponse du serveur PHP intégré sont plus lents que PHP-FPM + OPcache : **les scores réels en production
  devraient être égaux ou meilleurs**.
- Accessibilité : **axe-core** (WCAG 2.0/2.1 A et AA + bonnes pratiques), `tests/e2e/a11y.mjs`.
- Navigateur réel : `tests/e2e/smoke.mjs` (animations, mouvement réduit, sans JavaScript, Livewire, back-office).

## Résultats Lighthouse (mobile)

| Page | Perf. | Access. | Bonnes prat. | SEO | FCP | LCP | TBT | CLS |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Accueil | **95** | 100 | 100 | 100 | 1,6 s | 2,7 s | 20 ms | 0 |
| Formations | **98** | 100 | 100 | 100 | 1,4 s | 2,3 s | 10 ms | 0 |
| Actualités (liste, 9 vraies photos) | **92** | 100 | 100 | 100 | 1,5 s | 3,3 s | 20 ms | 0 |
| Article (10 photos) | **94** | 100 | 100 | 100 | 1,5 s | 3,0 s | 10 ms | 0 |
| Contact (Livewire) | **98** | 100 | 100 | 100 | 0,9 s | 1,9 s | 40 ms | 0 |

Objectifs du cahier des charges : performance ≥ 90, accessibilité / SEO / bonnes pratiques ≥ 95 → **atteints sur les 5 pages**.
(La liste d'actualités a ensuite reçu un correctif : image principale en chargement prioritaire, qui faisait baisser le LCP.)

Poids des pages (compressé) : accueil ≈ 140 Ko de HTML/CSS/JS + polices (≈ 115 Ko, mises en cache) ; JavaScript du site ≈ 40 Ko
(+ GSAP ≈ 47 Ko différé ; Livewire ≈ 99 Ko seulement sur Contact et Recherche) ; aucune vidéo ; images AVIF/WebP responsives.

## Accessibilité

- **axe-core : 0 violation** sur 13 pages × 2 largeurs (390 et 1440 px) : accueil, formations (liste et fiche), actualités (liste et
  article), événements, galerie, équipe, contact, recherche, pages importées, 404.
- Contrôles manuels intégrés : lien d'évitement, un seul `h1` par page, hiérarchie des titres sans saut (y compris dans le contenu
  importé : les titres parasites issus des légendes de galerie Divi sont supprimés), focus visible, menu plein écran piégeant le focus
  (Échap, `aria-expanded`), formulaires étiquetés avec erreurs reliées (`aria-describedby`), contrastes AA vérifiés par test.
- À faire par un humain avant l'ouverture : passage au lecteur d'écran (NVDA/VoiceOver) sur 3 parcours (accueil → formation →
  contact), et vérification des **textes alternatifs** réels (aujourd'hui provisoires pour les images à la une importées).

## Sécurité

- CSP stricte à nonce (pas d'`unsafe-eval`), `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS en production.
- Formulaire : CSRF, validation serveur, 3 envois/min/IP, honeypot, délai minimal verrouillé, IP stockée hachée.
- Contenu riche assaini (liste blanche) à l'affichage, y compris le contenu importé ; uploads limités (JPEG/PNG/WebP/PDF, 10 Mo).
- Back-office : 2FA TOTP, limitation de débit de la connexion, jamais de suppression de son propre compte ni du dernier super-admin.
- Tests automatisés : voir `tests/Feature/Public/SeoSecurityTest.php` et `tests/Feature/Admin/AdminSecurityTest.php`.
- À faire sur le serveur réel : note SSL Labs, `securityheaders.com`, `composer audit` / `npm audit`.

## Commandes

```bash
npm run build                                   # build de production
php artisan test                                # Pest
npm run e2e                                     # test de fumée navigateur (serveur lancé, données de démonstration ou importées)
MSYS_NO_PATHCONV=1 node tests/e2e/a11y.mjs      # axe-core (sous Git Bash ; sinon sans le préfixe)
node tests/e2e/prod-like-server.mjs 8081 http://127.0.0.1:8002   # relais proche production pour Lighthouse
npx lighthouse@12 http://127.0.0.1:8081/ --only-categories=performance,accessibility,best-practices,seo
```
