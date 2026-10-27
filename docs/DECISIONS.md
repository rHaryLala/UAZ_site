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

## D-013 · Médiathèque = modèle `MediaAsset` (et non des fichiers attachés à chaque contenu)
- **Décision** : chaque image/PDF est un `MediaAsset` (titre, **texte alternatif obligatoire**, légende, crédit) portant
  un unique fichier Media Library. Les contenus référencent ces éléments par identifiant (couverture, galeries, blocs).
- Alternatives : `SpatieMediaLibraryFileUpload` directement sur chaque modèle. Écartée : impossible dans les blocs
  flexibles (Builder), pas de réutilisation, pas de texte alternatif centralisé.
- Conséquences : sélecteur `MediaPicker` (recherche, miniatures, envoi sans quitter le formulaire), envoi multiple
  avec alt obligatoire pour chaque fichier, compression de l'original à 2560 px, dimensions mémorisées (anti-CLS).

## D-014 · Pages : arbre + clé système + blocs JSON
- **Décision** : `pages.path` (chemin complet, unique) recalculé à l'enregistrement ; `pages.key` identifie les pages
  « système » (home, about, admissions…) non supprimables ; blocs de contenu en JSON (`[{type, data}]`).
- Les sections dynamiques (formations, actualités, événements, galerie, équipe, contact, recherche) ont des routes
  dédiées ; leurs slugs sont réservés (`Page::RESERVED_SLUGS`).

## D-015 · Aperçu des brouillons
- **Décision** : le lien « Aperçu » du back-office ajoute `?apercu=1` à l'URL publique ; les contrôleurs publics
  (phase 4) y afficheront aussi les brouillons pour un compte connecté ayant `content.view`.
- Alternative : URLs signées temporaires. Écartée : plus lourd pour des rédacteurs non techniques.

## D-016 · Brouillon imposé côté serveur
- **Décision** : en plus du formulaire (l'option « Publié » n'est pas proposée), le modèle (`Publishable`) rétrograde
  en brouillon tout enregistrement « publié » fait par un compte sans `content.publish`.

## D-017 · Données de la maquette utilisées comme source
- **Décision** : le seeder de structure reprend les chiffres, mentions/parcours, horaires et e-mail de la maquette
  fournie par l'équipe (`docs/MAQUETTE.md`) ; ils sont à valider. Le **téléphone n'est pas pré-rempli** car la
  maquette donne deux numéros différents.
- Les contenus factices (`DemoContentSeeder`) sont limités à l'environnement local, préfixés « (Démo) » et supprimables
  avec `php artisan demo:purge`.

## D-018 · Export CSV des messages sans dépendance
- **Décision** : export streamé via une action Filament (BOM UTF-8, séparateur `;`, neutralisation des formules
  `= + - @`), sans les tables `exports`/`imports` de Filament ni file d'attente.

## D-019 · Transitions de page : View Transitions natives plutôt que Swup
- **Décision** : `@view-transition { navigation: auto }` (CSS), fondu court ; désactivé en mouvement réduit.
- Alternative : Swup. Écartée : Swup remplace le `<body>` sans recharger les scripts, ce qui complique Alpine, Livewire et
  le nettoyage des ScrollTriggers ; l'API native est stable (Chrome/Edge/Safari) et n'affecte pas les autres navigateurs.

## D-020 · Alpine « CSP » + Livewire seulement quand nécessaire
- **Décision** : Alpine en build CSP (pas d'eval, donc CSP stricte possible) ; composants déclarés en JS. Livewire n'est
  chargé que sur les pages contenant un composant Livewire (contact, recherche) : bundle principal ≈ 1 Ko gzip,
  Alpine ≈ 23 Ko, Livewire +99 Ko uniquement sur ces deux pages. GSAP/Lenis sont chargés après le rendu.
- Filament garde son propre Alpine (non compatible CSP stricte) : `livewire.csp_safe` reste à `false`.

## D-021 · Pas de photos de la maquette
- Les photos de la maquette sont des illustrations ; seules des photos réelles de l'UAZ seront utilisées. En attendant,
  les images manquantes sont remplacées par la silhouette de Madagascar sur fond marine.

## D-022 · Composant d'icônes `x-ico`
- `x-icon` est déjà enregistré par blade-icons (dépendance de Filament) et masquait mon composant : renommé `x-ico`.

## D-023 · Import WordPress : ce qui est importé, et ce qui ne l'est pas
- **Importé** : auteurs (comptes **sans rôle**, e-mail `@import.invalid`, pour signer les articles), 18 articles, 12 pages
  (rattachées aux pages système du nouveau site via `PAGE_MAP`), 92 membres de l'équipe, 3 témoignages vidéo, redirections.
- **Médias** : uniquement ceux **référencés** par le contenu importé (≈ 75 sur 529). Les ~420 médias orphelins et les
  fichiers > 10 Mo ou hors types autorisés (Word, `yearbook-2025.pdf` de 66,8 Mo) ne sont pas copiés : ils sont listés
  dans `docs/IMPORT-REPORT.md` avec l'action à mener.
- **Non importé volontairement** : `home`, `accueil-2`, `s-a`, `aumonerie`, `cours-a-la-carte`, `blog`, `academique`,
  `contactez-nous` (voir AUDIT §7).

## D-024 · Idempotence et respect des modifications des rédacteurs
- Table `import_records` (source, identifiant, modèle local, empreinte du contenu source). Relancer l'import ne crée
  rien en double et ne met à jour que ce qui a changé sur WordPress.
- Un contenu **modifié dans le back-office depuis le dernier import n'est jamais écrasé** (il est signalé « à relire ») ;
  `--force` permet de le remplacer.

## D-025 · Nettoyage du HTML Divi
- Suppression des shortcodes, commentaires Gutenberg, scripts, styles en ligne, classes, `data-start/data-end` ;
  déroulage des conteneurs `et_pb_*` ; `h1` → `h2` ; paragraphes entièrement en gras (artefact d'éditeur) dégraissés ;
  images → références à la médiathèque (`<figure data-media>`) rendues en images responsives AVIF/WebP ;
  images cliquables menant à un PDF sans texte → lien texte « Télécharger … » ; iframes YouTube → lien.
- Liens internes réécrits d'après `docs/URL-MAP.csv` et les nouveaux slugs ; les liens non résolus sont rapportés.
- Le HTML importé repasse par l'assainisseur à l'affichage (liste blanche) : l'import n'est pas une zone de confiance.

## D-026 · Textes alternatifs des images importées
- Aucun média de l'ancien site n'a de texte alternatif. Les images **à la une** reçoivent un alt **provisoire**
  (« Illustration : titre de l'article »), les portraits « Portrait de Nom » ; chacun est listé dans le rapport.
  Les autres images restent à renseigner dans la médiathèque (filtre « Sans texte alternatif »).

## D-027 · Conversions AVIF après l'import
- L'import désactive l'AVIF (très lent) ; lancer ensuite `php artisan media-library:regenerate` pour le produire.

## D-028 · Accueil identique à la maquette (27 octobre 2026)
- À la demande de l'équipe (« vraiment identique »), l'en-tête et le héros reprennent la capture de la maquette : barre du haut
  (devise + Étudiants · Notre équipe · Alumni · Donner · Se connecter), en-tête clair collé en haut, navigation inline,
  bouton « S'inscrire » bleu à angles droits, héros sans motif triangle/Madagascar, boutons or à angles droits, bandeau bas
  « Madagascar / Université francophone ». Le triangle et la silhouette restent dans « Notre devise ».
- Le bleu `#3856a6` et l'or `#c9a84c` de la maquette sont ajoutés comme jetons (`royal`, `sun`) à côté de la palette du sceau :
  ils servent à l'en-tête et aux boutons d'action de l'accueil ; le reste du site garde le marine du sceau.
- Serif remplacé par **Libre Baskerville** (plus large que Fraunces) sur tout le site pour rester cohérent ; échelle des titres réduite.
- Menu éditable « topbar » (barre du haut) ajouté ; le menu principal passe à À propos · Formations · Admissions · Vie étudiante · Recherche
  (Actualités reste dans « Pied de page »). Sous 1280 px, le bouton « Menu » ouvre le menu plein écran.
- Orthographe : « Vie étudiante » (la maquette écrit « Vie estudiantine », faute probable) ; apostrophes typographiques dans le titre.

## D-029 · Menu plein écran animé avec GSAP
- Le bouton « Menu » est visible à toutes les largeurs (bureau compris). Il ouvre un menu plein écran animé par GSAP
  (`resources/js/animations/menu.js`) : rideau or puis panneau marine ouverts en cercle depuis le bouton, liens en cascade.
- Contenu : menu principal avec sous-pages, **ressources et pages utiles** (menu « Pied de page » du back-office : Actualités,
  Notre équipe, Événements, Galerie, Emplois du temps, Téléchargements, Contact), liens de la barre du haut non déjà présents,
  bouton « S'inscrire », coordonnées et réseaux.
- Repli : sans GSAP (économiseur de données, chargement en cours) ou en mouvement réduit, le menu s'affiche avec un simple fondu CSS.

## D-030 · Recherche en fenêtre modale
- La loupe (et « Recherche » dans la navigation, Ctrl/Cmd + K, « / ») ouvre une fenêtre de recherche avec résultats en direct,
  groupés (formations, actualités, événements, pages). Sans JavaScript, la loupe reste un lien vers `/recherche`.
- Pas de Livewire sur toutes les pages (≈ 99 Ko) : la fenêtre charge un fragment HTML (`/recherche/apercu`, limité à 60 requêtes/min)
  rendu par le serveur ; le moteur (`App\Services\SiteSearch`) est partagé avec la page `/recherche`.
- Alpine CSP interdit `x-html` : le fragment est inséré par `innerHTML` depuis notre propre serveur (valeurs échappées par Blade).
