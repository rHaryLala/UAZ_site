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

## D-031 · Rubriques sans page parente, inscription en ligne
- « Admissions » et « Vie étudiante » sont des rubriques : leurs pages parentes sont **non publiées** (elles répondent par une
  redirection vers leur première sous-page), les sous-pages gardent leurs adresses (`/admissions/licence`, `/vie-etudiante/cafeteria`…).
  Dans la navigation, ces rubriques ouvrent une liste et ne sont pas des liens.
- Les boutons « S'inscrire » / « Candidater » mènent à `config('uaz.registration_url')`
  (https://registrar.zurcher.edu.mg/#pre-inscription, réglable par `UAZ_REGISTRATION_URL`), dans un nouvel onglet.

## D-032 · Yearbook numérique (livre à feuilleter)
- Lien « Yearbook » dans la barre du haut → `/yearbook` : livre 3D à feuilleter (bibliothèque `page-flip`, MIT) avec ombres de pages,
  couvertures rigides, curseur, miniatures, plein écran, flèches du clavier ; page seule sur mobile.
- Le PDF d'origine (66,8 Mo, 44 pages A4) n'est pas servi tel quel : ses pages sont rendues en WebP (1100 px, ≈ 190 Ko chacune, 9 Mo au total
  avec les miniatures) dans `public/books/yearbook/2025/` (versionné), générées par `scripts/yearbook-pages.mjs`. Les images ne se chargent
  qu'à l'approche de la page courante. Le PDF reste téléchargeable depuis l'ancien site.
- Le dossier n'est pas `public/yearbook` : un dossier du même nom que la route `/yearbook` serait servi à sa place par le serveur web.
- CSP : `page-flip` crée sa propre balise `<style>` ; on lui donne le nonce de la page pendant son chargement (`resources/js/yearbook.js`)
  plutôt que d'assouplir la politique.
- Nouvelle édition : générer les images, ajouter l'année dans `config/uaz.php` (`yearbooks`), le sélecteur d'éditions s'affiche tout seul.

## D-033 · Météo du campus dans le pied de page
- Température et temps du campus sous l'adresse, via **Open-Meteo** (gratuit, sans clé ni cookie ; attribution « Open-Meteo » affichée).
  L'appel est fait par le serveur (`App\Services\CampusWeather`), mis en cache 10 minutes avec rafraîchissement en arrière-plan : pas d'adresse
  IP de visiteur envoyée à un tiers, aucun assouplissement de la CSP, et une panne du service n'affiche simplement rien.
- Rafraîchi toutes les 10 minutes dans la page ouverte (`/meteo`, `resources/js/weather.js`). Désactivable : `UAZ_WEATHER=false` (désactivé dans les tests).
- Coordonnées approximatives (Sambaina, Antsirabe II, GeoNames) dans `config/uaz.php` : suffisantes pour la météo, pas pour un repère de carte.

## D-034 · Back-office en mode SPA
- `->spa(hasPrefetching: true)` sur le panneau Filament : navigation Livewire (`wire:navigate`) sans rechargement du document, préchargement au survol.
- Vérifié par `tests/e2e/admin-spa.mjs` (document conservé entre menu, liste, édition et médiathèque ; retour arrière ; zone de dépôt montée après navigation).
- Les scripts personnalisés du back-office doivent survivre à la navigation : la zone de dépôt utilise Alpine (`x-data`), nettoyé et remonté par Livewire ;
  éviter les `DOMContentLoaded` ou les écouteurs `window` hors Alpine dans les vues du back-office.

## D-035 · Sélecteur de médias façon WordPress

**Contexte :** l'ancien champ (liste déroulante Filament) affichait « [object Object] » et ne montrait pas les images.
**Décision :** champ dédié `App\Filament\Forms\MediaField` (fenêtre Alpine, grille, recherche, détails, envoi avec texte alternatif obligatoire, sélection multiple pour les albums), alimenté par `MediaBrowserController` (`/admin/media-browser`, soumis aux droits `MediaAsset`). `MediaPicker::make()` garde sa signature : aucun formulaire à modifier. Grille à cellules de taille fixe (les hauteurs en pourcentage s'écrasaient au chargement de la suite).
**Limites :** les PDF sont représentés par une vignette dessinée, pas par leur première page.

## D-036 · Médiathèque en grille : chargement progressif

**Décision :** en vue grille, `ListMediaAssets::loadMoreMedia()` augmente de 36 le nombre de fichiers par page (plafond 1000) au lieu de changer de page ; un crochet de rendu (`resources/views/filament/media/load-more.blade.php`) déclenche l'appel à l'arrivée en bas et propose un bouton de secours. La pagination Filament est masquée en grille, conservée en liste. Recherche et filtres remettent le compteur à 36.

## D-037 · Médiathèque : grille par défaut et bascule rapide

**Décision :** la vue grille est la vue par défaut. La bascule grille/liste utilise `redirect(..., navigate: true)` (navigation SPA, sans recharger CSS/JS/polices) et la table de la médiathèque n'est plus en chargement différé. Mesure en développement : bascule de ~8-11 s à ~1,4 s.

## D-038 · Pas de bandeau de cookies ; page « Confidentialité et cookies »

**Constat :** le site ne dépose que deux cookies techniques (session `universite-adventiste-zurcher-session` et `XSRF-TOKEN`, 2 h), n'a aucun outil de mesure ni traceur tiers (CSP `connect-src 'self'`, météo récupérée côté serveur). Ces cookies sont exemptés de consentement : **pas de bandeau**.
**Décision :** page d'information `privacy` (créée en brouillon par `StructureSeeder`, éditable dans le back-office), liée dans le bas du pied de page seulement quand elle est publiée (`SiteComposer::privacyPage`). Un bandeau ne sera nécessaire que si un outil de statistiques ou un contenu tiers intégré est ajouté.

## D-039 · Fenêtre d'abonnement à la lettre d'information

**Décision :** fenêtre `<dialog>` (`components/site/newsletter-dialog.blade.php`, `resources/js/newsletter.js`) ouverte à la première visite (après 6 s), puis 20 jours après la dernière ouverture tant que l'abonnement n'a pas été demandé. Mémoire dans `localStorage` (`uaz.newsletter` : `seenAt`, `subscribed`), donc **sans cookie** ; si le stockage est interdit, pas d'ouverture automatique. Jamais par-dessus une autre fenêtre ni sur le Yearbook. Bouton « S'abonner » (`#newsletter`) dans la barre du haut juste après Yearbook (migration + seeder) et dans le menu plein écran.
**Abonnement :** `POST /newsletter` (JSON, 6 requêtes/min, champ piège) → table `newsletter_subscribers` → e-mail de confirmation (lien signé, 7 jours) ; désinscription par lien signé. Pas de double inscription ni d'e-mail répété sous 10 min ; réponse identique pour une adresse déjà inscrite (on ne révèle pas qui l'est).
**Limites :** l'envoi de la lettre n'est pas implémenté (export CSV des abonnés actifs depuis l'admin). Le mailer de développement est `log` : en production, configurer un vrai SMTP.

## D-040 · Page Bibliothèque : visite immersive en six scènes (GSAP)

**Décision :** la page `student-life-library` a sa propre mise en page (`PageController` → `pages/library.blade.php`, styles `resources/css/library.css`, animations `resources/js/animations/library.js`, chargées seulement sur cette page). Scènes : (1) enseigne qui se rapproche puis iris sur la salle de lecture (ScrollTrigger pin + scrub, `clip-path`) ; (2) citation dont les mots s'allument (SplitText) ; (3) panorama horizontal épinglé de la salle avec trois cartes (`containerAnimation`) ; (4) rayonnage de cinq « livres » cliquables (clavier : flèches) ; (5) horaires avec état « ouvert maintenant » calculé à l'heure de Madagascar (`Intl`, fuseau `Indian/Antananarivo`), ligne du jour, onglets Ouverture / Prêt, affiche inclinée à la souris ; (6) « Bienvenue chez vous ! » lettre à lettre et cadre dévoilé.
**Contenu :** uniquement le texte de la page importée (accueil, citation, contact, lien ebooks) et les horaires de l'affiche officielle (transcrits en tableau, accessibles et lisibles par les moteurs). Rien d'inventé ; le samedi, absent de l'affiche, est signalé « non communiqué ».
**Images :** enseigne et affiche = les deux images du texte importé (dans l'ordre) ; la salle de lecture = champ « Image » de la page.
**Sobriété :** amélioration progressive. Sans JavaScript, en « réduire les animations » ou en économiseur de données, la page est complète (cartes en pile, fiches du rayonnage listées, tableaux d'horaires visibles). Sur mobile, pas de scène horizontale.

## D-041 · Héros de la Bibliothèque : visite en vidéo pilotée par le défilement

**Décision :** le héros de la page Bibliothèque est la visite en drone (22 premières secondes de `DJI_20260819095635_0097_D.MP4`), lue au rythme du défilement : écran épinglé sur quatre hauteurs d'écran, la position de la vidéo suit celle de la page (avance et recule, lissée). Le titre s'efface au départ ; « Bienvenue chez vous ! » apparaît à la fin.
**Technique :** deux encodages H.264 sans son, 24 i/s, **chaque image est une image clé** (`-g 1`, sans images B) : le déplacement est immédiat dans les deux sens (mesuré : 394 des 407 images affichées pendant un défilement continu, contre 270 avec une image clé toutes les 8) : `public/videos/library-720.mp4` (7,9 Mo) et `library-360.mp4` (≈ 3 Mo, petit écran ou connexion limitée), plus l'affiche `library-poster.webp`. La vidéo est téléchargée une fois en mémoire (`blob:`) avant de servir au défilement : le déplacement ne dépend alors ni des requêtes par plages d'octets (absentes du serveur de développement PHP) ni du réseau. La CSP autorise donc `media-src 'self' blob:`.
**Repli :** sans JavaScript, en « réduire les animations » ou en économiseur de données, l'affiche (première image) reste affichée. Si les fichiers vidéo sont absents (ils ne sont pas dans Git), la photo de la salle de lecture sert de héros fixe.

## D-042 · Page Cafétéria : six scènes GSAP, identité chaude

**Décision :** `student-life-cafeteria` a sa propre mise en page (`PageController` → `pages/cafeteria.blade.php`, `resources/css/cafeteria.css`, `resources/js/animations/cafeteria.js`, chargés seulement sur cette page). Palette tirée des photos (terracotta, vert feuille, crème, bleu de la nappe). Scènes : (1) « Cafétéria » tombe lettre à lettre (SplitText, `bounce.out`), soulignement tracé (DrawSVG), photo dans une assiette ceinte de mots qui tournent, plus vite quand on défile (vitesse ScrollTrigger) ; (2) citation épinglée : trois phrases qui s'allument, soulignements tracés, fond qui passe du vert au terracotta puis au bleu ; (3) six mots entourés à la main à leur arrivée et au survol ; (4) triptyque de trois volets (flex animé, survol/clic/clavier, un bouton `aria-expanded` par volet) ; (5) bandeau « Paix · Fraternité · Gratitude » dont le sens, la vitesse et l'inclinaison suivent le défilement ; (6) phrase d'invitation dont les mots arrivent des deux côtés.
**Contenu :** uniquement le texte importé (titre, citation, principes, accueil, ambiance) et ses trois photos dans l'ordre (service, cuisine, équipe) ; aucun horaire ni menu inventé. Chaque mot n'apparaît qu'une fois (voir D-040 sur les redondances).
**Sobriété :** sans JavaScript ou en « réduire les animations », tout est lisible (citation entière, volets empilés ou ouvrables, bandeau en un groupe). Petit écran : volets empilés, pas de scène horizontale.

## D-041 (complément) · Qualité de la vidéo de la Bibliothèque

La vidéo est maintenant éclaircie à l'encodage (`eq` gamma 1,18, léger `unsharp`) et proposée en trois niveaux choisis selon l'écran et la connexion : `library-1080.mp4` (≈ 19 Mo, écrans ≥ 1300 px physiques), `library-720.mp4` (≈ 13 Mo, tablettes), `library-360.mp4` (≈ 5 Mo, mobile ou connexion limitée) ; toujours en images toutes clés. Le voile sombre du héros est réduit à 8 % pendant la visite.

## D-043 · Page Dortoirs : une journée, de l'aube à la nuit

**Décision :** `student-life-dorms` a sa propre mise en page (`PageController` → `pages/dormitories.blade.php`, `resources/css/dormitories.css`, `resources/js/animations/dormitories.js`, chargés seulement sur cette page). Fil conducteur : « Votre second foyer », vu comme une journée ; les fonds glissent de la crème de l'aube au ciel, au coucher de soleil, au crépuscule puis à la nuit, la lumière ambrée des fenêtres étant l'accent. Scènes : (1) « Dortoirs » pousse lettre à lettre (`scaleY`, `back.out`), une arche sur l'allée du campus s'ouvre jusqu'au plein écran en défilant (pin + `clip-path`), soleil qui s'efface ; (2) phrase d'accueil révélée ligne à ligne (SplitText masqué) ; (3) deux résidences dont les chiffres montent (80 / 68), cartes qui se croisent, photo qui suit la souris ; (4) schéma illustratif du rez-de-chaussée du nouveau dortoir (12 cases : 8 chambres de 4, réception, salle à manger, 2 chambres de la Maison du Précepteur) qui se construit depuis le centre et dont les étages prévus se tracent (DrawSVG) ; (5) silhouette de bâtiments dont les fenêtres s'allument puis vacillent.
**Contenu :** uniquement le texte importé (capacités 80 / 68, chambres de 3–4 et 4–5, projet R+2, vision) et ses trois photos dans l'ordre (allée, résidence des filles, résidence des garçons). Les deux liens Facebook (« Open House », « MJA ») sont lus dans le texte importé, jamais retapés. Le lien « Soutenir ce projet » mène à la fiche du projet existante. Le schéma est annoncé comme illustratif et hors échelle : l'agencement exact des chambres n'est pas fourni.
**Sobriété :** grand écran : scènes épinglées ; mobile : schéma construit une fois à l'arrivée, pas d'épinglage ; sans JavaScript ou en « réduire les animations » : tout est lisible (chiffres, 12 cases visibles, fenêtres partiellement allumées).

## D-043 (complément) · Photos de chambres : héros et mur de photos

Six photos de chambres fournies par l'UAZ sont ajoutées au texte de la page (figures 4 à 9, après les trois photos d'extérieur) : la 4e (chambre aux rideaux à tournesols) devient le héros (dans l'arche) ; les cinq suivantes forment un **mur de photos** (« Dans les chambres ») : polaroïds de travers qui tombent à l'arrivée, déplaçables et « lançables » à la souris (Draggable + Inertia ; écrans ≥ 768 px avec souris seulement, pour ne pas bloquer le défilement tactile). Un mot du texte de présentation par photo en légende : Camaraderie, Apprentissage (garçons), Concentration, Amitié, Croissance personnelle (filles). L'allée du campus passe dans la section d'accueil (arche). Sans ces photos (production non mise à jour), le héros reprend l'allée et le mur disparaît. Coût : le module de la page passe à ≈ 47 Ko (18 Ko compressé), chargé uniquement sur cette page.

## D-044 · Pages Admission Licence et Master : « le dossier »

**Décision :** les pages `admissions-licence` et `admissions-master` partagent une mise en page (`PageController` → `pages/admissions.blade.php`, `resources/css/admissions.css`, `resources/js/animations/admissions.js`, chargés seulement sur ces pages) avec deux identités tirées des affiches officielles : Licence (marine, jaune de la « chemise jaune ») et Master (violet des toges, turquoise, « dossier à scanner » avec ligne de balayage). Données structurées dans `config/admissions.php` (dates, pièces du dossier, frais, mentions, matières de test et séries de Bac, prérequis de Master), transcrites du texte des pages et des affiches « Admission 2026-2027 ».
**Scènes :** (1) titre découpé en mots, décompte des jours avant la prochaine date, photo dévoilée, sceau de l'université qui tourne ; (2) calendrier épinglé : un point suit le chemin des dates (MotionPath + DrawSVG), chaque date s'éclaire à son passage (chaque date est « passée / prochaine étape / à venir » d'après la date du jour à Madagascar, calculée côté serveur) ; (3) chemise qui s'ouvre en 3D, pièces cochées au défilement (DrawSVG), compteur, tampon « Dossier complet » ; (4) cartes de mentions qui se réorganisent à l'ouverture (Flip), avec lien vers la fiche de formation ; (5) coordonnées et affiche officielle inclinée à la souris.
**Fiabilité :** le calendrier n'est jamais présenté comme « ouvert » : seuls les états réels sont affichés. Les formulaires à télécharger sont lus dans les liens du texte importé (jamais retapés). Sans JavaScript ou en « réduire les animations », tout est lisible ; mobile : pas d'épinglage ni de scène horizontale.

## D-045 · Page Frais académiques : un relevé que l'on compose

**Constat :** les montants n'existaient que dans six images de tableaux (illisibles sur mobile, non indexables, impossibles à comparer). Ils sont transcrits dans `config/fees.php` (Licence : 2 départements × 2 semestres × 4 années × interne/externe ; Master : M1 et M2) et exposés par `App\Support\FeeCatalog`.
**Garde-fou :** les totaux imprimés sur les tableaux sont conservés dans la config et un test (`FeesPageTest`) vérifie que, pour chacune des 35 colonnes, la somme des lignes les retrouve exactement : une faute de transcription ou de mise à jour fait échouer le test. Aucun tarif n'est calculé ailleurs.
**Page :** (1) registre (filets de comptabilité tracés en DrawSVG, titre mot à mot) ; (2) simulateur : on choisit niveau, département, année, régime et période, un ticket de caisse s'imprime (clip-path), ses lignes se posent, le total monte, une barre montre la répartition (frais généraux, écolage, dortoir et cantine, laboratoires, autres) et, en Licence, une comparaison interne / externe ; (3) plans de paiement du Master découpés en parts (100 %, 50 + 50, 4 × 25 %) et moyens de paiement avec bouton « Copier » ; (4) les six tableaux officiels en tableaux HTML accessibles (`<details>`, en-têtes, défilement horizontal) avec liens vers les images ; (5) coordonnées du service Finance.
**Honnêteté des chiffres :** « Année complète » = somme des deux semestres du tableau (les lignes présentes aux deux semestres sont marquées « Cumul des deux semestres ») ; le M2 n'a qu'un semestre sur le tableau, « Année complète » y est désactivée et l'écran l'explique ; le test de niveau (20 000 Ar) est signalé mais exclu du total ; aucune estimation, aucun arrondi.
**Sobriété :** le simulateur (`resources/js/fees-sim.js`, moins de 5 Ko) fonctionne sans les animations (économiseur de données, « réduire les animations ») ; sans JavaScript, il est masqué et les six tableaux restent lisibles.
**Correctif au passage :** sans JavaScript, le menu plein écran du site restait posé par-dessus la page (la classe utilitaire `fixed` l'emportait sur la règle de repli) et bloquait les clics ; la règle de repli est maintenant prioritaire (`resources/css/app.css`).

## D-046 · Éditeur visuel des pages (première version)

**Besoin :** modifier la page elle-même, comme sur Elementor, plutôt qu'un formulaire séparé.
**Décision :** éditeur plein écran (`/admin/visual-editor/{page}`, `VisualEditorController`, `public/js/admin/visual-editor.js` pour la coque et `visual-canvas.js` pour la page affichée dans une iframe, sans les scripts du site : pas d'animation pendant l'édition). Le serveur dessine la page avec les vrais composants du site en « mode édition » (`EditMode` : enveloppe de bloc et images du texte riche, jamais présentes sur le site public) ; le navigateur n'est jamais cru sur parole : tout est validé et nettoyé côté serveur (`VisualBlocks::normalize` : types, champs, longueurs, liens autorisés, HTML assaini, images existantes ; souple pour un brouillon, strict pour publier).
**Source unique des blocs :** `App\Support\VisualBlocks::types()` décrit les champs de chaque bloc ; elle pilote le formulaire de réglages, la validation et les blocs neufs. Un test vérifie qu'elle reste alignée sur `PageBlocks::TYPES` (formulaire classique).
**Texte en place sans toucher aux vues :** le script de la page repère chaque texte qui correspond exactement à un champ texte du bloc et le rend éditable ; le texte riche est éditable en entier (images conservées par leur identifiant). Aucune des huit vues de blocs n'a été modifiée.
**Brouillon :** colonne `pages.draft` (titre, résumé, blocs). La page publiée n'est modifiée qu'à « Publier » (droit `content.publish` exigé pour une page publiée). Enregistrement automatique après 1,4 s d'inactivité ; historique de 120 étapes (annuler / rétablir) côté navigateur.
**Pages sur mesure :** `VisualBlocks::BESPOKE_KEYS` liste les pages dont la mise en page est dessinée à la main (leur contenu ne vient pas des blocs) ; l'éditeur l'explique au lieu de risquer de les abîmer.
**Suite possible (non faite) :** (1) rendre éditables les textes des pages sur mesure (surcharge des textes par clé, en commençant par À propos) ; (2) nouveaux blocs de mise en page (colonnes, espaceur, bouton, accordéon / FAQ, vidéo, héros avec image) ; (3) glisser les blocs directement sur la page ; (4) styles par bloc (fond, marges, alignement) ; (5) modèles de pages et blocs enregistrés ; (6) historique des versions publiées avec retour en arrière ; (7) édition à plusieurs (verrou par page).

## D-047 · Site en anglais et en malagasy

**Besoin :** versions anglaise et malagasy du site, sans casser les adresses françaises.
**Décision :** préfixes `/en` et `/mg` (le français reste sans préfixe, adresses inchangées, slugs identiques dans toutes les langues). Les routes publiques
sont enregistrées une fois par langue ; la langue courante préfixe la racine des URL générées (`URL::forceRootUrl`), si bien qu'aucun des 98 gabarits n'a
eu à changer ses liens. Pas de redirection automatique selon le navigateur : la langue est celle de l'adresse (partageable, indexable), avec `hreflang`.
**Interface :** dictionnaires `lang/{langue}.json` dont la clé est la phrase française (`__('Voir la formation')`) plutôt que des clés techniques :
les vues restent lisibles et une phrase non traduite s'affiche en français. `php artisan i18n:check` et un test (`TranslationsTest`) garantissent
qu'aucune phrase ni variable ne manque. Les fichiers par groupe (`lang/en/home.php`…) portent les longs textes éditoriaux.
**Contenu :** colonne JSON `translations` + trait `HasTranslations` plutôt que `spatie/laravel-translatable` : les colonnes d'origine (français) ne changent pas,
donc ni migration de données, ni requêtes, recherche, tris ou slugs à adapter ; repli automatique sur le français. Onglets « Traductions » dans les formulaires.
**Pièges évités :** cache des menus par langue et par version du dictionnaire ; mises à jour Livewire sans préfixe mais langue rétablie (middleware persistant) ;
météo en cache sans libellé ; `Locales::tr()` car `__('About')` ouvrait `lang/en/about.php` sous Windows ; espaces insécables français conservés via `lang/fr.json`.
**À faire (voir TODO-CONTENU) :** relecture du malagasy par un locuteur natif ; traduction du contenu importé dans le back-office.

## D-048 · Page « Centre de recherche » immersive, bâtie sur les seuls faits publiés

**Besoin :** donner à la page du centre le même traitement immersif que la Bibliothèque ou la Cafétéria.
**Contrainte :** l'UAZ n'a publié presque rien sur ce centre (ni mission, ni axes, ni publications, ni chiffres). Rien n'est inventé.
**Décision :** `pages/research.blade.php` (+ `research.css`, `animations/research.js`) ; la mise en scène porte la page, pas un texte inventé :
héros « observatoire » (réseau de points qui se tisse, parallaxe à la souris), phrase officielle de présentation des formations qui s'allume mot à mot
pendant que le fil se trace (écran épinglé sur grand écran), fiches de l'équipe, domaine du directeur et ses mentions, Master, invitation à écrire.
**Données :** équipe = fiches de l'équipe dont la fonction contient « Centre de recherche » (le directeur d'abord) ; domaine = la faculté nommée par la
fonction du directeur ; sigle = ce qui est entre parenthèses dans le titre de la page ; phrase = texte de présentation du site actuel ; Master = texte de
`lang/*/about.php`. Une section sans donnée disparaît.
**Contenu à venir :** les blocs de la page ajoutés dans le back-office s'affichent en fin de page ; ceux qui portent la mention `TODO-CONTENU` ne sont
jamais montrés au public ; tant qu'il n'y en a pas, une phrase sobre annonce que la présentation détaillée sera publiée. Page ajoutée à
`VisualBlocks::BESPOKE_KEYS` (l'éditeur visuel renvoie au formulaire classique).
**Accessibilité et mouvement :** base complète sans JavaScript ; mouvement réduit = tout visible, rien d'épinglé ; sur mobile, la phrase s'allume au
défilement sans épinglage. Audit axe-core sans violation (français, anglais, malagasy). Test e2e : `tests/e2e/research.mjs`.

## D-049 · Page « Soutenir l'UAZ » immersive : un chantier qui se construit

**Besoin :** même traitement immersif que les autres pages de vie du campus, sans promettre ni inventer.
**Décision :** `pages/support.blade.php` (+ `support.css`, `animations/support.js`). Les deux projets sont des constructions : la page est un chantier.
Héros photo (champ « Image » de la page) ; **scène épinglée** où la phrase d'introduction s'allume mot à mot pendant qu'un mur de briques monte rangée par
rangée (décor, aucun chiffre) ; une **planche par projet** (budget compté à l'arrivée puis restitué tel que saisi, plan dessiné quand il n'y a pas de photo) ;
**barres de montants** proportionnelles aux budgets publiés ; les deux « portes » (don en ligne, contact) ; invitation finale.
**Ce que la page ne fait pas :** aucune somme « déjà réunie », aucune jauge de progression, aucun total (rien n'est publié). Les barres n'apparaissent que si
au moins deux projets ont un budget dans la même unité (le premier nombre du champ « Budget » est lu).
**État du don en ligne :** inchangé et honnête (`UAZ_DONATION_URL` vide : « Bientôt disponible » et contact ; renseigné : bouton « Faire un don » dans le
héros, la porte et l'invitation). Contacts : `config/uaz.php` (`donation_contacts`).
**Accessibilité et mouvement :** base complète sans JavaScript ; mouvement réduit = tout visible, mur complet, rien d'épinglé ; mobile sans épinglage.
Audit axe-core sans violation (fr, en, mg et fiche projet). Tests : `SupportPageTest`, `tests/e2e/support.mjs`.

## D-050 · Rubrique « Ressources » dans le menu principal

**Besoin :** regrouper les pages de service dans le menu du haut : Téléchargements, Galerie, Emplois du temps, Événements.
**Décision :** nouvelle entrée « Ressources » (liste déroulante, sans page parent), placée après « Vie étudiante ». « Téléchargements » y est rangé et quitte « Admissions » (un seul emplacement). Menu « header » du seeder + migration `add_resources_to_header_menu` pour les sites déjà installés (idempotente, réversible, ne touche pas un menu qui a déjà la rubrique). Les libellés existaient déjà en anglais et en malagasy ; le menu reste modifiable dans le back-office (Menus).
**Largeur :** avec six rubriques, la navigation horizontale s'affiche dès 1340 px (au lieu de 1280) avec des espaces resserrés ; en dessous, le bouton « Menu » (plein écran) montre aussi la rubrique. Le logo ne rétrécit plus (`shrink-0`). Mesuré en fr, en et mg de 1340 à 1536 px : ni chevauchement ni défilement horizontal.
Tests : `StructureAndRedirectImportTest` (menu semé), `ResourcesMenuMigrationTest`.

## D-051 · Page « Emplois du temps » immersive : un agenda qui se compose

**Besoin :** remplacer une liste de liens PDF par une page au design original, intuitive et immersive (effets GSAP).
**Décision :** `pages/timetables.blade.php` (+ `timetables.css`, `animations/timetables.js`, `App\Support\TimetableSheets`). Identité d'agenda : papier chaud, encre bleue, blocs de couleur (une couleur par mention).
Héros : un tableau d'emploi du temps se compose bloc après bloc, une ligne de temps le parcourt et allume les blocs qu'elle traverse, il suit le pointeur. Ruban des mentions qui accélère avec le défilement. **Fiches épinglées** sur grand écran : la page s'arrête, les fiches défilent sur le côté (progression, pastilles pour aller à une mention, clavier : une fiche hors champ focalisée est amenée à l'écran), chaque fiche pivote en arrivant et dessine son motif. Petit écran : fiches empilées qui montent une à une. Invitation finale (contact).
**Données :** aucune n'est inventée. Les fiches viennent des liens PDF saisis dans le texte de la page (ajouter ou retirer un lien ajoute ou retire une fiche) ; le nom et la faculté viennent de la formation qui porte ce nom (comparaison sans accents), la taille du fichier du fichier lui-même. Les motifs dessinés sont du décor (toujours les mêmes pour une mention) et ne représentent aucun horaire. Aucune date, aucun semestre.
**Accessibilité et mouvement :** base complète sans JavaScript (grille de fiches) ; mouvement réduit = tout visible, rien d'épinglé ; liens PDF en nouvel onglet annoncés. Audit axe-core sans violation (fr, en, mg, mobile). Tests : `TimetablesPageTest`, `tests/e2e/timetables.mjs`.
Textes : `lang/{fr,en,mg}/timetables.php` (malagasy à faire relire).

## D-052 · Galerie photo immersive : une salle d'exposition

**Besoin :** remplacer une grille de vignettes par une galerie au design original, intuitive et immersive (effets GSAP).
**Décision :** `albums/index.blade.php` et `albums/show.blade.php` (+ `gallery.css`, `animations/gallery.js`). Identité de salle d'exposition : mur sombre, cadres à passe-partout clair, or.
Liste : héros « vernissage » (mur incliné de couvertures qui dérive en sens contraires, titre lettre à lettre, nombres d'albums et de photos comptés) ; **exposition** : accrochage irrégulier sur douze colonnes, chaque cadre à **son vrai format**, dévoilé par un rideau (clip-path), pivotant en arrivant, photo en parallaxe dans son cadre, **projecteur** qui suit la souris, **curseur « Ouvrir »**, mur qui s'incline avec la vitesse du défilement ; **filtre par année** réorganisé avec **Flip** (années tirées des albums, annoncé aux lecteurs d'écran, fonctionne aussi sans animation) ; suite (actualités, événements).
Page d'un album : couverture qui respire, mur de photos à leur format, même visionneuse `<dialog>` qu'avant (extraite dans `partials/gallery-dialog`, aussi utilisée par le bloc galerie).
**Données :** rien n'est inventé. Les couvertures, titres, dates et nombres viennent des albums (back-office : Albums photo) ; le filtre n'apparaît qu'avec plusieurs années et une seule page (au-delà de 24 albums, pagination, sans filtre) ; sans album : « Aucun album pour le moment. ».
**Accessibilité et mouvement :** base complète sans JavaScript ; mouvement réduit = tout visible, ni projecteur ni curseur, filtre sans animation ; projecteur et curseur réservés à la souris. Audit axe-core sans violation (fr, en, mg, mobile, album). Tests : `GalleryPageTest`, `tests/e2e/gallery.mjs` (crée puis supprime six albums « E2E … » via `tests/e2e/fixtures/gallery-albums.php`).
Textes : `lang/{fr,en,mg}/gallery.php` (malagasy à faire relire).

## D-053 · Page « Événements » immersive : un cadran de l'année et un fil du temps

**Besoin :** remplacer deux listes de cartes par une page au design original, intuitive et immersive (effets GSAP).
**Décision :** `events/index.blade.php` (+ `events.css`, `animations/events.js`, `App\Support\EventDial`). Identité d'agenda vivant : nuit indigo, corail (les rendez-vous), soleil (aujourd'hui), papier chaud.
Héros : un **cadran de l'année** (cercle de douze mois, dessiné avec DrawSVG) sur lequel aujourd'hui et les événements des douze prochains mois glissent depuis le 1er janvier jusqu'à leur place, avec un arc d'aujourd'hui au prochain ; **compte à rebours** du prochain événement (jours, heures, minutes, secondes, chiffres qui roulent). Programme : **fil** qui se remplit au défilement, un point par rendez-vous, date qui monte, carte qui glisse, photo dévoilée par un rideau puis en parallaxe, curseur « Voir ». Archives : événements passés en noir et blanc qui arrivent par vagues (`ScrollTrigger.batch`) et retrouvent leurs couleurs au survol (9 par page). Suite : lettre d'information (ouvre la fenêtre d'abonnement) et actualités.
**Données :** tout vient des événements saisis (dates, lieu, image, inscription) ; aucun événement ni date inventés. « Dans 10 jours », « Demain », « Aujourd'hui », « En cours » sont calculés des dates ; un événement en cours remplace le compte à rebours ; sans événement à venir, message explicite et cadran sans prochain rendez-vous. Les chiffres du compte à rebours sont rendus côté serveur puis avancent au navigateur.
**Accessibilité et mouvement :** base complète sans JavaScript ; le compte à rebours et le cadran sont masqués aux lecteurs d'écran (une phrase « Dans N jours » les remplace, sans annonce chaque seconde) ; mouvement réduit = tout visible, le compte à rebours avance quand même sans animation ; curseur réservé à la souris. Audit axe-core sans violation (fr, en, mg, mobile). Tests : `EventsPageTest`, `tests/e2e/events.mjs` (crée puis supprime des événements « E2E … » via `tests/e2e/fixtures/events.php`).
La page d'un événement (`events/show`) n'a pas encore reçu ce traitement. Textes : `lang/{fr,en,mg}/events.php` (malagasy à faire relire).

## D-054 · Actualités immersives : un journal (liste et page d'un article)

**Besoin :** remplacer la grille de cartes par un design original, intuitif et immersif, pour la liste des actualités et pour ses sous-pages (articles).
**Décision :** `news/index.blade.php`, `news/show.blade.php`, `components/news-tile.blade.php` (+ `news.css`, `animations/news.js`). Identité de journal : papier chaud, encre bleue, filets, rouille, et un **surligneur jaune** qui se déploie sous les titres au survol.
Liste : titre de journal géant (lettres qui se lèvent) avec filet double et date de la dernière publication ; **ruban** des six derniers titres qui défile, accélère et s'incline avec le défilement ; **article à la une** (le plus récent de la sélection, première page seulement) dont le texte déborde sur la photo ; fil de cartes sur un accrochage irrégulier (7 + 5, puis 4 + 4 + 4) dont le filet se trace, la photo se dévoile par un rideau puis glisse dans son cadre ; curseur « Lire » ; rubriques et pagination **sans rechargement** (les nouvelles cartes se dévoilent grâce à l'événement `uaz:region` émis par `ajax-region.js`) ; lettre d'information et événements en fin de page.
Article : photo d'ouverture qui respire et glisse, titre mot à mot, **barre de lecture** qui se remplit, temps de lecture **calculé du texte** (200 mots par minute), **lettrine**, intertitres au filet qui se trace, citations mises en valeur, photos du texte dévoilées par un rideau, **rail de partage** qui reste à portée (Facebook, WhatsApp, copier le lien), article **précédent / suivant** en grands panneaux, « À lire aussi ».
**Données :** tout vient des articles (titre, chapeau, rubrique, date, auteur, photo, texte) ; rien n'est inventé. Sans photo : illustration de Madagascar sur fond bleu.
**Accessibilité et mouvement :** base complète sans JavaScript (le bouton « Copier le lien » n'apparaît qu'avec le script) ; mouvement réduit = tout visible, ni barre de lecture ni curseur, copie du lien toujours disponible ; titres de niveaux cohérents (h1, h2 à la une, h3 cartes ; h2 cartes dès la page 2). Audit axe-core sans violation (fr, en, mg, mobile, liste, rubrique, article). Tests : `NewsPageTest`, `tests/e2e/news.mjs` (crée puis supprime trois articles « E2E … » et une rubrique via `tests/e2e/fixtures/news.php`).
Textes : `lang/{fr,en,mg}/news.php` (malagasy à faire relire). `x-news-card` reste utilisé par l'accueil.
