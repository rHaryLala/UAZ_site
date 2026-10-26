# Design system

Catalogue vivant : <http://localhost:8000/design-system> (désactivé en production, `config/uaz.php`).
Source des tokens : `resources/css/app.css` (bloc `@theme`).

## 1. Concept créatif

**« Esprit, Main, Intelligence — au Service »** : *Spiritus · Manus · Mens · Service*.
Le sceau est un triangle (pointe en bas) portant ces trois mots autour de la silhouette de Madagascar.
Deux motifs graphiques en découlent et reviennent dans tout le site, toujours en filigrane ou en séparateur :

| Motif | Composant | Usage |
| --- | --- | --- |
| **Triangle** (trait fin, pointe en bas) | `<x-triangle>` | filigrane des héros et bandeaux, cadre des trois piliers de l'accueil |
| **Madagascar** (silhouette vectorisée) | `<x-madagascar>` | filigrane, masque, remplissage quand une image manque |
| **Sceau** | `<x-seal>` | en-tête, pied de page ; **jamais déformé** (carré, `object-contain`) |

La silhouette vient du sceau lui-même : `node scripts/trace-madagascar.mjs` la détoure et la vectorise
(`resources/svg/madagascar.svg`, ≈ 4,5 Ko). Les angles un peu « carrés » proviennent du traitement du mot
« Service » qui la recouvre sur le logo : acceptable pour un motif stylisé, à remplacer par un tracé vectoriel
fourni par l'UAZ si elle en dispose.

## 2. Couleurs (extraites du logo)

Extraction : `node scripts/extract-logo-colors.mjs` ; nuances : `node scripts/build-palette.mjs`.

| Couleur du sceau | Valeur | Token |
| --- | --- | --- |
| Bleu marine | `#232E66` | `navy-800` |
| Or / bronze | `#8A724B` | `gold-600` |
| Noir | `#060807` | `ink-950` |
| Blanc | `#FFFFFF` | `paper-50` |

Échelles `navy`, `gold`, `ink` (50 → 950) et `paper` (50 → 300, blanc cassé chaud `#FBF9F4` pour le fond du site).
Les nuances claires sont calculées en OKLCH en conservant la saturation (sinon l'or devient un beige terne).

### Paires de couleurs autorisées (WCAG AA, texte normal ≥ 4,5 : 1)

| Usage | Texte / fond | Ratio |
| --- | --- | --- |
| Texte courant | `ink-800` / `paper-100` | 11,7 : 1 |
| Texte secondaire | `ink-600` / `paper-100` | 7,6 : 1 |
| Titres | `navy-800` / `paper-100` | 12,1 : 1 |
| Texte sur marine | blanc / `navy-800` ou `navy-900` | 12,7 / 16,8 : 1 |
| Texte doré sur clair | `gold-700` / `paper-100` | 6,7 : 1 |
| Texte doré sur marine | `gold-300` / `navy-800` | 5,8 : 1 |
| Bouton or | `navy-900` / `gold-300` | 7,7 : 1 |
| Or du sceau (`gold-600`) | sur blanc seulement, **grands textes et graphismes** | 4,6 : 1 (4,35 sur papier : pas pour du texte) |

Règle : ne jamais mettre `gold-600` ou plus clair en texte courant sur fond clair ; ne jamais mettre `gold-700` ou
plus foncé sur marine. Le catalogue recalcule les ratios en direct et un test échoue si l'un devient insuffisant.

## 3. Typographie

| Rôle | Police | Détails |
| --- | --- | --- |
| Titres, chiffres clés, citations | **Fraunces** (variable, axe optique) | serif expressif, graisse 420, `text-wrap: balance` |
| Texte, interface, formulaires | **Inter** (variable) | lisible, chiffres tabulaires disponibles |

Polices **auto-hébergées** (`@fontsource-variable`), sous-ensemble latin uniquement (couvre é è ç œ « »),
`font-display: swap`, **préchargées** dans le `<head>` (≈ 115 Ko au total). Aucun appel à Google Fonts ni Bunny.

Échelle fluide (390 → 1440 px) : `text-display`, `text-h1` … `text-h4`, `text-lead`, `text-figure`, et l'utilitaire
`eyebrow` (surtitre en capitales espacées).

## 4. Composants Blade

| Composant | Rôle |
| --- | --- |
| `<x-layouts.app>` | gabarit : `<head>` SEO, lien d'évitement, en-tête, pied de page |
| `<x-site.header>` / `<x-site.footer>` | en-tête (transparent sur héros, opaque au défilement), menu plein écran, pied en 4 colonnes |
| `<x-button>` | `gold` (sur marine), `navy` (sur clair), `outline`, tailles `md`/`sm` |
| `<x-section>` | section avec surtitre, titre, introduction, tons `paper`/`white`/`sand`/`navy` |
| `<x-news-card>`, `<x-formation-card>`, `<x-person-card>`, `<x-event-card>`, `<x-testimonial-card>` | cartes de contenu |
| `<x-key-figures>` | chiffres clés (valeur finale dans le HTML, compteur animé en option) |
| `<x-cta-banner>` | bandeau d'appel à l'action marine |
| `<x-page-hero>` | en-tête de page intérieure |
| `<x-breadcrumbs>` | fil d'Ariane (`aria-current="page"`) |
| pagination | `vendor/pagination/uaz.blade.php`, vue par défaut |
| `<x-picture>` | image responsive AVIF/WebP, dimensions explicites, lazy sauf `eager` |
| `<x-rich>` | texte riche **assaini** (liste blanche), jamais de HTML brut |
| `<x-ico>` | pictogrammes inline (le nom `x-icon` est pris par blade-icons de Filament) |

Classes utilitaires : `container-site`, `container-prose`, `section-y`, `btn btn-*`, `link-arrow`, `card`,
`stretched-link`, `field-label` / `field-input` / `field-error`, `prose-uaz`, `skip-link`, `on-dark`.

`.on-dark` (sections marine) inverse automatiquement les surtitres, boutons contour et liens, et change la couleur
de l'anneau de focus (or clair).

## 5. Accessibilité intégrée

- Lien d'évitement, `<main id="contenu">`, landmarks `nav` nommés, un seul `h1` par page.
- Focus visible partout (anneau 3 px marine ; or clair sur fond sombre).
- Menu plein écran : `role="dialog"`, focus piégé (`x-trap`), Échap, retour du focus sur le bouton.
- Cibles tactiles ≥ 44-48 px ; formulaires avec libellés, aide et erreurs reliées (`aria-describedby`).
- `prefers-reduced-motion` : aucun déplacement, pas de défilement fluide, menu en simple fondu.
- Sans JavaScript : tout le contenu est lisible, le menu s'affiche à plat, aucune animation ne masque du contenu.

## 6. Animations (principes)

- Animer `transform` et `opacity` uniquement ; GSAP + ScrollTrigger + SplitText, Lenis pour le défilement.
- `html.js` posé dans le `<head>` ; les états initiaux (`[data-reveal]`) n'existent qu'avec JS, avec un filet de
  sécurité CSS (affichage forcé après 3 s si le module n'a pas démarré).
- Modules à la demande (`resources/js/animations/*`), `gsap.matchMedia()` pour le mouvement réduit.
- Transitions entre pages : **View Transitions API** native (`@view-transition`), sans bibliothèque ; voir DECISIONS.

## 7. Outils

| Commande | Effet |
| --- | --- |
| `node scripts/extract-logo-colors.mjs` | couleurs dominantes du logo |
| `node scripts/build-palette.mjs [--css]` | échelles de nuances + contrastes |
| `node scripts/trace-madagascar.mjs` | vectorise la silhouette de Madagascar |
| `node scripts/build-logo-assets.mjs` | sceau en WebP/PNG, favicons |
| `node tests/e2e/shots.mjs <dossier> /chemin…` | captures 390/768/1440 + contrôle des erreurs et du débordement |
| `node tests/e2e/slice.mjs <image> [hauteur]` | découpe une capture longue pour l'inspecter |
| `node tests/e2e/menu.mjs` | vérifie le menu plein écran (focus, Échap, ARIA) |
