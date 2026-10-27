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

## Site public (phase 4)

| Route | Contrôleur / composant | Notes |
| --- | --- | --- |
| `/` | `HomeController` + `resources/views/home/*` | sections : héros, différence UAZ, piliers, formations par domaine, admissions, vie à l'UAZ, apprentissage pratique, témoignages, actualités, événements, ressources, partenaires |
| `/formations`, `/formations/{slug}` | `FormationController` | filtres niveau / domaine en liens (sans JS) |
| `/actualites`, `/actualites/{slug}` | `NewsController` | filtre catégorie, pagination (9) |
| `/evenements`, `/galerie`, `/equipe` | contrôleurs dédiés | album = visionneuse `<dialog>` |
| `/contact` | `ContactController` + `Livewire\ContactForm` | honeypot, délai mini 3 s, 3 envois/min/IP, e-mail au bureau |
| `/recherche` | `Livewire\Search` | LIKE (SQLite/MySQL), formulaire GET sans JS |
| `/{chemin}` | `PageController` | pages du back-office par chemin complet ; **toujours en dernier** |

- **Visibilité** : scope `visible()` (publié et date passée) ; `?apercu=1` ajoute brouillons et programmés pour un compte
  du back-office (en-têtes `no-store` et `noindex`). Les `Route::bind` ne résolvent que du contenu visible (sinon 404).
- **Contenu éditorial** : textes de l'accueil dans `lang/fr/home.php` (prêts pour l'anglais) ; titre/sous-titre du héros,
  chiffres clés, formations, actualités, témoignages, partenaires dans le back-office.
- **Cache** : `SiteSetting`, `Menu::resolved()` et la table de redirections sont mis en cache ; on y stocke des tableaux,
  jamais des modèles (Laravel 13 interdit leur désérialisation par défaut).

## Animations

```
resources/js/
  app.js                 entrée minuscule : choisit boot/alpine.js ou boot/livewire.js, lance les animations
  boot/                  Alpine CSP seul, ou Livewire (qui embarque Alpine CSP) quand la page a un composant Livewire
  components/            Alpine.data : site-menu, carousel, lightbox (aucune expression inline complexe : build CSP)
  animations/
    index.js             charge, après le rendu, uniquement les modules dont la page a besoin
    gsap.js              enregistrement unique de GSAP + ScrollTrigger + SplitText ; nettoyage pagehide/pageshow
    smooth-scroll.js     Lenis synchronisé avec ScrollTrigger (désactivé en mouvement réduit)
    reveal.js            [data-reveal] : opacité + translation au défilement
    hero.js              SplitText mot par mot, triangle (stroke-dashoffset), Madagascar, parallaxe
    counters.js          [data-counter] : compteurs (valeur finale déjà dans le HTML)
    pillars.js           Spiritus · Manus · Mens : triangle dessiné au scroll (scrub)
    parallax.js          [data-parallax]
```

Règles : `transform`/`opacity` seulement ; `gsap.matchMedia()` pour `prefers-reduced-motion` ; états initiaux masqués par CSS
uniquement sous `html.js`, avec filet de sécurité (affichage forcé après 1,4 à 3,5 s) ; le contenu est toujours dans le HTML.
Transitions entre pages : View Transitions API native (voir DECISIONS D-019).

## Import WordPress (phase 5)

```
app/Services/Wordpress/
  WordpressClient.php    GET uniquement, ≥ 600 ms entre requêtes, User-Agent identifié, refus des chemins du robots.txt
  HtmlCleaner.php        Divi/Gutenberg → HTML propre ; images → <figure data-media> ; liens internes réécrits
  MediaImporter.php      médias référencés → médiathèque (taille/type contrôlés, alt conservé, conversions)
  WordpressImporter.php  orchestrateur : users, pages, posts, team, testimonials, redirects (idempotent)
  ImportReport.php       compte rendu Markdown (docs/IMPORT-REPORT.md)
app/Models/ImportRecord  correspondance source ↔ local (empreinte du contenu, date de synchronisation)
```

Les images d'un texte importé sont des `<figure data-media="ID">` : `App\Support\Html::render()` les remplace à l'affichage par une
image responsive (AVIF/WebP, dimensions explicites) de la médiathèque, après assainissement.

## Sécurité, SEO et performance (phase 6)

| Sujet | Mise en œuvre |
| --- | --- |
| En-têtes | `SecurityHeaders` : CSP stricte **à nonce** (pas d'`unsafe-eval`, pas d'`unsafe-inline` pour les scripts), `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS en production. Le back-office Filament n'a pas de CSP stricte (non compatible) |
| Formulaire de contact | validation serveur, CSRF Livewire, 3 envois/min/IP, honeypot, délai minimal **verrouillé** (`#[Locked]`), IP stockée hachée |
| Contenu riche | `App\Support\Html` : liste blanche (symfony/html-sanitizer), pas d'iframe, pas de style/script/handler |
| Uploads | images JPEG/PNG/WebP et PDF, 10 Mo, extensions et MIME contrôlés ; compression automatique |
| Comptes | 2FA TOTP, limitation de débit de la connexion, impossible de supprimer son compte ou le dernier super-admin |
| SEO | `sitemap.xml` (cache 1 h), `robots.txt` dynamique (fermé hors production), canonical, Open Graph/Twitter, JSON-LD : `CollegeOrUniversity`, `WebSite`, `BreadcrumbList`, `Course`, `Article`, `Event` |
| Performance | images AVIF/WebP responsives avec dimensions, polices latines préchargées, JS différé et découpé, mode Save-Data sans GSAP, HTML compressible (≈ 10×) |
