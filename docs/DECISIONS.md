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
**Abonnement :** `POST /newsletter` (JSON, 6 requêtes/min, champ piège) → table `newsletter_subscribers` → ~~e-mail de confirmation (lien signé, 7 jours)~~ *(supprimé : voir D-073, l'abonnement est immédiat)* ; désinscription par lien signé. Réponse identique pour une adresse déjà inscrite (on ne révèle pas qui l'est).
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

## D-055 · Page d'un événement immersive : l'affiche, le billet, le déroulé

**Besoin :** donner à la page d'un événement le même traitement que la liste des événements (D-053).
**Décision :** `events/show.blade.php` (+ styles et animations ajoutés à `events.css` et `events.js`, `animations/prose.js` partagé avec les actualités, `App\Support\EventCalendar`). Héros « affiche » : la photo respire et glisse, le titre se lève mot à mot, le **compte à rebours** du début de l'événement avance chaque seconde, et le **billet** (talon corail avec la date, corps de papier avec horaire, lieu, plan, inscription, agenda, perforation dessinée par un masque) se pose en pivotant et suit le pointeur. **Déroulé** tiré des dates saisies : une **journée de 24 h** où la plage horaire se remplit (horaire connu), un point (début sans fin), la journée entière, **les jours** de l'événement qui se posent l'un après l'autre (2 à 14 jours), ou une phrase (plus de 14 jours). Descriptif (texte riche, sans lettrine) puis les trois prochains autres rendez-vous.
**« Ajouter à mon agenda » :** fichier `.ics` (route `events.ics`, `/evenements/{événement}/agenda.ics`, lignes pliées à 75 octets, heures en UTC, journée entière en dates) et lien Google Agenda. Sans heure de fin saisie, aucune n'est supposée.
**Données :** rien n'est inventé : « Dans N jours », « En cours », « Événement terminé » sont calculés des dates ; un événement passé n'a ni compte à rebours, ni inscription, ni agenda. Les brouillons restent introuvables (page et `.ics`).
**Accessibilité et mouvement :** base complète sans JavaScript ; la date, l'horaire et le lieu sont de vrais textes dans le billet ; le compte à rebours est masqué aux lecteurs d'écran (la pastille « Dans N jours » le dit sans annonce chaque seconde) ; mouvement réduit = tout visible, le compte à rebours avance quand même. Audit axe-core sans violation (fr, en, mg, mobile, quatre types d'événements). Tests : `EventPageTest`, `tests/e2e/events.mjs` (section « Page d'un événement »). Textes : `lang/{fr,en,mg}/events.php` (malagasy à faire relire).

## D-056 · Connexion au back-office : une vitrine de vraies photos et un « regard » qui réagit

**Besoin :** donner à la page de connexion du back-office (`/admin/login`) un design original et immersif, avec les interactions d'une page de connexion « vivante » (le regard suit le pointeur, cligne à l'entrée d'un champ, se ferme quand le mot de passe est visible, secousse sur une erreur). Un premier essai (une aube illustrée avec soleil et collines) a été écarté : trop « jeunesse ». On garde les interactions, avec de vraies photos de l'université.
**Décision :** on habille le formulaire de Filament sans le remplacer (aucune logique de connexion touchée) : `AdminPanelProvider` ajoute trois « render hooks » limités aux pages de connexion et de mot de passe oublié (`SIMPLE_LAYOUT_START` : la vitrine ; `SIMPLE_LAYOUT_END` : lien « Retour au site public » ; `HEAD_END` : feuille de style et script Vite `admin-login`). À gauche, une **vitrine de photos** (trois colonnes de hauteurs inégales) sur deux couches : photos éteintes (noir et blanc, sombres) et mêmes photos en couleurs vues à travers un **regard** (masque en ellipse + anneau fin à quatre repères). À droite, le formulaire posé sur du papier (sombre en mode sombre).
**Photos (`App\Support\LoginPhotos`) :** uniquement de vraies photos déjà publiées sur le site : images d'accueil et de formations des réglages, couvertures des pages, puis couvertures d'actualités qui sont de vraies photos d'appareil (noms `IMG_…`, `DSC_…`, `UAZ…`, format 3:2). En paysage et d'au moins 1 600 px de large ; ni portraits, ni affiches, ni captures d'écran. Six au plus, mises en cache dix minutes. Moins de trois photos : sceau seul, sans vitrine. Rien n'est ajouté ni inventé.
**Interactions (ce que fait le regard) :** il suit le pointeur et les photos s'inclinent vers lui ; il cligne en entrant dans un champ et de temps en temps tout seul ; sur écran tactile il dérive doucement et suit la saisie le long du champ ; il s'ouvre plus grand à mesure que le formulaire se remplit ; il **se ferme** (et les photos s'estompent) quand le mot de passe est visible et se rouvre quand il est masqué ; sur une erreur Filament les photos se secouent et le regard cligne deux fois ; à l'envoi puis à la connexion il s'ouvre en grand. À l'arrivée : photos dévoilées par un rideau, regard qui s'ouvre, titre mot à mot, champs qui montent. Les collines, étoiles et soleil du premier essai sont supprimés.
**Sécurité :** inchangée : même formulaire, mêmes limites de tentatives, même 2FA ; la vitrine est décorative (`aria-hidden`), sans donnée ; le back-office garde sa politique d'en-têtes actuelle (pas de CSP stricte, voir `SecurityHeaders`).
**Isolation :** tous les styles sont limités à `body:has(.uaz-scene)` et le script démonte la scène en quittant la page (navigation SPA) : le reste du panneau n'est pas touché.
**Accessibilité et mouvement :** base complète sans JavaScript (regard fixe au centre) ; mouvement réduit = regard fixe, sans transition, mais il se ferme quand même avec le mot de passe visible et s'ouvre avec la saisie ; audit axe-core sans violation (clair, sombre, mot de passe oublié, mobile). Mobile : la vitrine devient un bandeau en haut. Tests : `AdminLoginPageTest`, `tests/e2e/admin-login.mjs` (crée puis supprime un compte).
La vidéo de référence (« Cute Login Page Animation ») n'a pas pu être visionnée : les interactions reprises sont celles du modèle HTML fourni (yeux qui suivent le pointeur, clignement au focus, yeux fermés mot de passe visible, secousse sur envoi).

## D-057 · Accueil immersif (hors héros et section vidéo)

**Besoin :** donner à l'accueil le même traitement immersif que les autres pages, **sans toucher** au héros ni à la section « Votre futur commence ici » (texte en glitch + vidéo), qui gardent leurs vues, styles et animations.
**Décision :** `home/*.blade.php` (difference, formations, admissions, life, practice, testimonials, news, events, resources, partners, closing), `home.css`, `animations/home.js`. L'ordre de la page est inchangé.
1. **La différence :** la phrase s'allume mot à mot sur un écran arrêté (grand écran), puis le texte apparaît ; les quatre chiffres clés, géants, se comptent (`counters.js`) sous un filet d'or qui se trace.
2. **Les domaines de formation :** quatre panneaux numérotés qui s'ouvrent au survol, au clavier (focus) et au toucher ; le panneau ouvert élargit son nom et dévoile ses mentions. Fonctionne aussi avec « réduire les animations » et sans JavaScript (le premier panneau est ouvert).
3. **Admissions :** le sceau dans deux anneaux tracés (DrawSVG), la devise tourne autour, le sceau s'incline vers le pointeur ; boutons attirés par le pointeur.
4. **La vie à l'UAZ :** bibliothèque, cafétéria, dortoirs, avec de **vraies photos** (image de la page, sinon première photo de son texte) : les cartes plein écran s'empilent, chacune recouvre la précédente qui recule et s'assombrit. La section disparaît si aucune des trois pages n'est publiée (avant : titre sans contenu, la page « Vie étudiante » étant un brouillon).
5. **Apprentissage pratique :** le titre en lettres géantes défile avec la page.
6. **Témoignages :** grande citation mot à mot, guillemet géant en parallaxe ; correction d'accessibilité : le rôle « diapositive » passe de l'élément de liste à son contenu (c'était la dernière violation axe de l'accueil).
7. **Actualités :** l'article à la une en grand et deux vignettes, mêmes composants que la page Actualités (`news.js`). **Événements :** billets avec date en talon, le premier avec son **compte à rebours** (`events.js`) ; section absente sans événement. **Ressources :** sommaire de grandes lignes ; l'image de la page (si elle en a une) apparaît et suit le pointeur. **Partenaires :** ruban de logos qui défile et ralentit au survol (absent sans partenaire). **Fermeture :** la photo glisse, le titre arrive mot à mot (remplace `x-cta-banner` sur l'accueil seulement).
**Données :** rien n'est inventé : textes existants (`lang/*/home.php`), chiffres clés des réglages, formations, actualités, événements, pages et photos publiés.
**Accessibilité et mouvement :** les mots « éteints » de la phrase restent à 50 % d'opacité (contraste 3:1 du grand texte) ; épinglage des cartes sur leur contenu (la liste ne contient que des `li`) ; mouvement réduit = rien d'épinglé, tout visible ; mobile sans épinglage. Audit axe-core : 0 violation (fr, en, mg, mobile). Tests : `HomePageTest`, `tests/e2e/home.mjs`.
**Au passage :** le ruban de la page Actualités ne montre que les titres de la rubrique choisie.

## D-058 · Page « Système LMD » immersive, rangée dans la rubrique Ressources

**Besoin :** donner à `/systeme-lmd` le même traitement immersif que les autres pages, et la ranger dans la rubrique **Ressources** du menu.
**Décision :** `pages/lmd.blade.php` (+ `lmd.css`, `animations/lmd.js`, `config/lmd.php`, `lang/{fr,en,mg}/lmd.php`). Page sur mesure (hors éditeur visuel, `VisualBlocks::BESPOKE_KEYS`).
1. **Les trois lettres :** L, M, D géantes (initiales des grades) dans trois cartes ; elles suivent le pointeur à des profondeurs différentes et la carte se remplit d'or au survol ; chacune mène à sa section.
2. **La montée :** écran arrêté sur grand écran ; un marqueur gravit un **escalier** (une marche par grade, la 4e en pointillés pour la suite), une **jauge de crédits ECTS** se remplit (180 pour la Licence puis 300) avec son compteur, et le texte de chaque grade remplace le précédent. Mobile et mouvement réduit : les trois grades sont lisibles l'un sous l'autre, l'escalier et la jauge sont complets.
3. **La Licence** (sept mentions avec leurs parcours, chaque mention renvoie à sa fiche de formation quand elle est publiée), **le Master** (M1 → M2 reliés par un trait, trois mentions) et **le Doctorat**.
**Données :** `config/lmd.php` reprend mot pour mot le texte de la page « Système LMD » du site actuel : trois ans, six semestres, 180 crédits ECTS ; deux ans (M1, M2), 120 crédits, 300 depuis le Bac ; mentions et parcours. « Bac+3 » et « Bac+5 » sont calculés de ces durées. **Rien n'est inventé :** le Doctorat n'est présenté que comme « poursuite » après le Master, sans durée ni crédit. Les textes passent par les dictionnaires (`Translated::deep`, inventaire `i18n:check`). Si le texte de la page change, `config/lmd.php` est à mettre à jour (voir docs/TODO-CONTENU.md).
**Menu :** « Système LMD » est ajouté à la fin de « Ressources » (seeder + migration `add_lmd_to_resources_menu`, idempotente et réversible pour les sites installés) ; il apparaît aussi dans la section « Ressources » de l'accueil.
**Accessibilité et mouvement :** base complète sans JavaScript ; grand texte et libellés à contraste conforme ; audit axe-core sans violation (fr, en, mg, mobile). Tests : `LmdPageTest`, `ResourcesMenuMigrationTest`, `tests/e2e/lmd.mjs`. Malagasy à faire relire.

## D-059 · Back-office habillé comme shadcn/ui (même pile : Laravel, Filament, Livewire, Tailwind)

**Besoin :** donner au back-office le langage visuel de shadcn/ui, sans changer de pile.
**Décision :** thème Filament dédié, `resources/css/filament/admin/theme.css` (entrée Vite, branché par `->viteTheme()` dans `AdminPanelProvider`). Il importe le thème de Filament puis le surcharge, hors couche CSS, donc sans `!important`.
1. **Palette :** variables du thème « neutral » de shadcn (`--background`, `--card`, `--muted`, `--accent`, `--primary`, `--border`, `--input`, `--ring`, `--sidebar`), en clair et en sombre. `gray` = `Color::Neutral` ; `primary` devient un noir (blanc en sombre) au lieu du bleu marine.
2. **Formes :** rayon de 8 px (`--radius`), bordures d'un pixel à la place des anneaux, ombres quasi nulles, anneau de focus de 3 px.
3. **Composants :** boutons de 36 px (plein noir, « outline » avec bordure), champs, cases et bascules, sections et tableaux en cartes, en-têtes de tableau discrets, pastilles, onglets en segment, menus et fenêtres, barre du haut et menu latéral séparés par un trait, entrée active du menu en fond « accent ».
4. **Sélecteur de médias, glisser-déposer, raccourcis :** leurs styles en ligne utilisent désormais `--uaz-accent` / `--primary-foreground` (sinon le bouton noir devenait invisible en mode sombre).
**Limite :** les couleurs sémantiques (succès, danger, avertissement) restent celles de Filament ; la page de connexion garde sa mise en scène propre.

## D-060 · Page « Association des étudiants » immersive, ajoutée au menu « Vie étudiante »

**Besoin :** une page pour l'Association des étudiants (SA), dans le sous-menu « Vie étudiante », à partir de la section 3.3 du Manuel de l'étudiant, moderne et dans la direction artistique du site, immersive avec GSAP.
**Décision :** page sur mesure `student-life-association` (`vie-etudiante/association-des-etudiants`), hors éditeur visuel (`VisualBlocks::BESPOKE_KEYS`) : `pages/association.blade.php`, `association.css`, `animations/association.js`, textes dans `lang/{fr,en,mg}/association.php`. Le texte du manuel est aussi enregistré tel quel dans la page (`StructureSeeder::ASSOCIATION_TEXT`), pour la recherche et l'édition.
1. **« SA » :** deux lettres géantes remplies d'une vraie photo du campus (couverture de la première page de la vie étudiante qui en a une), qui suivent le pointeur ; la photo glisse au défilement.
2. **Son rôle :** la phrase s'allume mot à mot, puis la chaîne VRAE → SA → activités se trace.
3. **Ses officiants :** une foule de points s'allume (tous les étudiants inscrits votent), quelques-uns grandissent (les élus). Décor : aucun chiffre n'est annoncé.
4. **Se présenter :** écran arrêté sur grand écran ; quinze sièges en demi-cercle (« au plus 15 noms »), une onde pour l'appel à candidature, puis le VRAE se relie à l'équipe (entretien). Mobile et mouvement réduit : étapes l'une sous l'autre, sièges remplis, VRAE et lien visibles.
5. **Son engagement :** les projets du VRAE passent en tête de la planification ; trois principes (promouvoir, dialoguer, prioriser) ; fermeture vers le manuel (page Téléchargements) et les autres pages de la vie étudiante.
**Données :** rien n'est inventé. Les seuls chiffres sont ceux du manuel : 15 (noms au plus) et les sections 3.3 et 3.4 ; les barres de la planification et les points de la foule sont décoratifs et sans légende chiffrée. Le terme « affaires estudiantines » du manuel est conservé.
**Menu :** entrée ajoutée à la fin de « Vie étudiante » (seeder + migration `add_student_association_page`, idempotente et réversible : elle crée aussi la page sur un site déjà installé, avec ses titres anglais et malagasy). En production, `php artisan migrate` suffit.
**Accessibilité et mouvement :** base complète sans JavaScript ; contraste conforme (phrases à l'opacité de départ ≥ 4,5:1) ; audit axe-core sans violation (fr, en, mg, mobile, avec et sans animations). Tests : `AssociationPageTest`, `StudentAssociationMigrationTest`, `tests/e2e/association.mjs`.

## D-061 · Remarques de la direction : don, catalogue de la bibliothèque, album aérien (5 novembre 2026)

**Besoin (retours reçus) :** (1) un accès direct au catalogue de la bibliothèque dès le haut du site ; (2) un chemin visible vers le don depuis l'accueil, plutôt que le seul lien « Donner » du menu ; (3) la page Bibliothèque doit offrir le catalogue en haut ; (4) l'album « drone » de la galerie doit porter un nom parlant.
**Décisions :**
1. **Barre du haut :** entrée « Catalogue de la bibliothèque » (site `library.zurcher.edu.mg`, nouvel onglet) après « Actualités » et avant « Donner » ; seeder + migration `add_library_catalogue_to_topbar_menu` (idempotente, réversible) pour les sites installés. Traduite (« Library catalogue », « Katalaogin'ny tranomboky »).
2. **Accueil, héros :** le second bouton « Planifier votre visite » (vers Contact) devient **« Soutenir l'UAZ »** (vers la page Soutenir l'UAZ), à côté de « Découvrir l'UAZ ». Seul le libellé et la destination de ce bouton changent ; le reste du héros est inchangé. Le lien « Donner » du menu reste.
3. **Page Bibliothèque :** bouton **« Accéder au catalogue »** dans le premier écran, sous la phrase d'accroche ; le lien de la section « La bibliothèque en ligne » reste plus bas.
4. **Galerie :** l'album « Vue Drone » devient **« L'UAZ vue de haut »** (adresse `/galerie/uaz-vue-de-haut`, anglais « UAZ from above », malagasy « Ny UAZ hita avy ambony »), avec une redirection 301 de l'ancienne adresse. C'est une donnée (back-office), appliquée sur le serveur de test ; il n'y a pas d'album sur la base de développement.
**À confirmer :** que `library.zurcher.edu.mg` est bien le catalogue visé (c'est le site de la bibliothèque déjà lié dans la page) ; le malagasy est à faire relire. Les textes alternatifs des photos de cet album sont « drone » (saisis à l'envoi) : à remplacer par une description réelle (ex. « Vue aérienne du campus »).
**Tests :** `HomePageTest` (bouton et barre du haut), `LibraryPageTest` (catalogue en haut et plus bas), `ResourcesMenuMigrationTest` (migration).

## D-062 · Refonte des traductions : anglais américain, vocabulaire d'université, malagasy corrigé (11 novembre 2026)

**Besoin :** traductions anglaises et malagasy jugées trop génériques (ex. « Formations » rendu par « Programmes »). Recherche préalable : les universités adventistes de référence ([Andrews](https://www.andrews.edu), [AUCA](https://web.auca.ac.rw/)) nomment cette rubrique **Academics** (liste : « Academic Programs »), en orthographe américaine ; les universités francophones, dans leur version anglaise, disent « Degree programs » ou « Study programmes ». « Education » a été écarté pour la rubrique : en anglais il désigne la pédagogie ou la formation des enseignants. Il reste utilisé là où il est exact (slogan « An education. A future. A vision. »).
**Décisions (choix de la direction : « Academics », orthographe américaine, réécriture des phrases) :**
1. **Menu et vocabulaire :** Formations → **Academics** ; mention → **major** ; parcours → **track** ; S'inscrire → **Apply** ; Emplois du temps → **Class schedules** ; Centre de recherche → **Research Center** ; interne/externe → **resident/commuter** ; Licence → « Bachelor's (Licence) ».
2. **Orthographe :** américaine partout (program, center, enroll, catalog, specialize, authorization, defense, installments…). L'ancien mélange (« programme », « enrol » et « enroll » côte à côte) est supprimé.
3. **Phrases réécrites** (accueil, À propos, événements, soutien, emplois du temps, LMD, formulaires, dossier d'admission) pour sonner anglais et non « français traduit ». Une erreur de sens corrigée : « selon mention » avait été rendu par « depending on the grade ».
4. **Malagasy (sans relecteur natif, à faire relire) :** corrections de faux sens (« Citation » rendu par « Tsiambaratelo » = secret → « Teny nalaina » ; « Affiche » rendu par « Efitra » = pièce → « Afisy » ; « Fanavaozana » → « Fahaiza-manokana » pour spécialisation), « programme » → « fandaharam-pianarana », « Téléchargements » → « Rakitra azo alaina », « S'abonner » → « Hahazo ny vaovao ».
5. **Nom du site traduisible :** « Zurcher Adventist University » / « Oniversite Advantista Zurcher » dans les titres d'onglet et le référencement des pages /en et /mg.
6. **Base de données :** migration `improve_english_translations` : remplace, dans les traductions déjà enregistrées (pages, formations, réglages), uniquement les valeurs qui sont **exactement** les anciennes du seeder ; une traduction saisie à la main n'est jamais écrasée. Réversible.
**Limites :** le contenu éditorial importé (actualités, équipe, descriptions d'images) reste à traduire dans le back-office. Le malagasy reste à faire relire (docs/TODO-CONTENU.md).

## D-063 · Lettres d'information : éditeur visuel sur plan de travail et envoi à chaque abonné (11 novembre 2026)

**Besoin :** composer les lettres dans le back-office et les envoyer par e-mail à chaque abonné actif, avec un outil « à la Canva » : on modifie la lettre directement sur un plan de travail.
**Décisions :**
1. **Éditeur visuel** (`/admin/newsletter-editor/{lettre}`, sur le modèle de l'éditeur de pages) : la lettre s'affiche telle qu'elle partira dans un cadre isolé (`iframe srcdoc`) ; textes modifiables sur place (gras, italique, lien, liste), barre flottante par bloc (monter, descendre, dupliquer, supprimer, glisser), panneau à quatre onglets (Ajouter, Structure, Bloc, Lettre : couleurs, police, bandeau), aperçu ordinateur/mobile, annuler/rétablir, enregistrement automatique.
2. **Neuf types de blocs** : titre, texte, image, bouton, citation, mise en avant, actualités du site, séparateur, espace. Le même dessinateur (`NewsletterRenderer`, tableaux HTML et styles en ligne pour les messageries) produit l'aperçu et le courrier.
3. **Le serveur ne croit jamais le navigateur** : `NewsletterBlocks::normalize` ne garde que les types connus, retire le balisage des champs simples, nettoie le texte enrichi, n'accepte que des couleurs `#rrggbb` et des liens `http(s)`, `mailto`, `tel` (60 blocs au plus).
4. **Destinataires :** abonnés *actifs* (confirmés, non désinscrits) au moment de l'envoi. Un travail de file par abonné (`SendNewsletterMessage`), espacés de `NEWSLETTER_SECONDS_BETWEEN` secondes (2 par défaut) pour respecter les limites de l'hébergeur ; un seul envoi par couple (lettre, abonné) (clé unique), 3 essais (1 puis 5 minutes), échec noté sans bloquer les autres ; un abonné désinscrit entre-temps est ignoré.
5. **Désinscription :** chaque courrier porte un lien personnel signé et l'en-tête `List-Unsubscribe` (bouton natif des messageries).
6. **Lettre figée après l'envoi :** brouillon → en cours → envoyée. Elle n'est plus modifiable ni supprimable, **même par le super-administrateur** (qui contourne les politiques d'accès) : la règle est posée dans le modèle et dans le contrôleur.
7. **Droit :** « content.publish » (éditeurs, super-administrateurs). Envoi d'essai à une adresse (objet préfixé `[TEST]`) sans toucher aux abonnés.
8. **Limites de fréquence propres à chaque action** (rendu 240/min, enregistrement 120, essai 10, envoi 5) : avec `throttle:N,1`, Laravel compte toutes les routes limitées d'un utilisateur ensemble, et les mises à jour du plan de travail épuisaient l'essai.
**À prévoir en production :** SMTP réel, SPF/DKIM du domaine, `queue:work` actif (docs/DEPLOIEMENT.md). **Limites :** pas de statistiques d'ouverture ni de clic ; pas de programmation d'envoi ni de segments.
**Tests :** `NewsletterEditorTest` (nettoyage, rendu, envoi, idempotence, désinscription, lettre figée, droits, écrans), `tests/e2e/newsletter-editor.mjs` (parcours complet dans un vrai navigateur).

## D-064 · Éditeur de lettres : mise en page libre (colonnes, bandeaux), modèles modernes, plan de travail instantané (11 novembre 2026)

**Besoin (retours reçus) :** (1) l'ajout d'un bloc mettait du temps à apparaître sur le plan de travail ; (2) des lettres modernes, avec des modèles proposés d'emblée ; (3) pouvoir vraiment composer : colonnes, photos n'importe où, blocs empilés (photo + texte), couleur réglable sur le bloc « Espace » ; (4) expéditeur dédié `newsletter@zurcher.edu.mg`.
**Décisions :**
1. **Blocs imbriqués.** Deux conteneurs : **Colonnes** (2 ou 3, proportions et écart au choix, alignement vertical, qui s'empilent sur mobile) et **Bandeau** (fond de couleur, marges, coins, bord à bord ou dans les marges ; sur fond sombre le texte des blocs passe en clair tout seul). Un conteneur reçoit n'importe quel bloc, photos comprises ; un bandeau peut porter des colonnes (deux niveaux au plus, `NewsletterBlocks::MAX_DEPTH`) ; 100 blocs au plus au total. Les colonnes ont des largeurs exactes en pixels (pas de `calc`, mal compris des messageries). `normalize` reste récursif et strict : un identifiant en double est renouvelé, un nombre de colonnes réduit verse ses blocs dans la dernière (rien n'est perdu), des proportions incompatibles reviennent à « égales ».
2. **Bloc « Espace »** : hauteur jusqu'à 160 px et **couleur** (transparent par défaut) ; posé au premier niveau il va d'un bord à l'autre de la lettre : bande colorée. Autres ajouts : titre en petites capitales espacées (surtitre), bouton sur toute la largeur, image au tiers de la largeur.
3. **Compositions toutes faites** dans « Ajouter » : photo + texte, texte + photo, deux ou trois photos, chiffres clés, bandeau clair ou foncé. Les blocs se **glissent** sur la lettre (ils tombent dans la colonne ou le bandeau visé, un trait montre l'endroit) ou s'ajoutent d'un clic ; un emplacement « Ajouter ici » apparaît dans chaque colonne ; la barre d'un bloc imbriqué a un bouton « ↰ conteneur » ; la structure est un arbre où l'on glisse d'une colonne à l'autre.
4. **Sept modèles** (`NewsletterTemplates`) choisis à la création d'une lettre (aperçu de la lettre entière) et dans l'éditeur (Lettre → « Choisir un modèle… », annulable) : Actualités du mois, Événement, Mot du recteur, Admissions, Galerie souvenirs, Soutenir l'UAZ, Lettre simple. Les photos sont posées d'office avec les dernières images de la médiathèque ; textes d'exemple à remplacer. Inspiration : lettres actuelles d'universités et d'ONG (grand titre sur fond uni, surtitre en capitales, chiffres clés, photos arrondies, un seul appel à l'action).
5. **Plan de travail instantané.** Cause de la lenteur : chaque action rechargeait toute la lettre depuis le serveur (aller-retour, nouvelle page dans l'iframe, images rechargées), et le serveur de développement (un seul processus, sans cache d'opcodes) les mettait en file derrière l'enregistrement automatique. Désormais : la lettre n'est rechargée en entier que pour un changement de couleurs/police ou un modèle ; sinon chaque **bloc de premier niveau est dessiné à part** (`/rows`), **mis en cache selon son contenu** (sans ses identifiants) et seules les lignes modifiées sont remplacées. Les blocs du panneau « Ajouter » sont **préparés d'avance** : ajouter, dupliquer, déplacer, annuler s'affichent sans attendre le serveur (mesuré : 40 à 90 ms, contre plusieurs centaines) ; un bloc en cours de dessin montre un squelette animé. Le serveur valide tout comme avant ; l'aperçu et le courrier restent produits par le même code.
6. **Expéditeur :** `NEWSLETTER_FROM_ADDRESS` (défaut `newsletter@zurcher.edu.mg`) et `NEWSLETTER_FROM_NAME`, distincts de `MAIL_FROM_ADDRESS` (messages du site) ; les réponses vont à l'adresse du site. Le compte SMTP doit avoir le droit d'envoyer au nom de cette adresse ; SPF et DKIM du domaine.
7. **Avant l'envoi,** la fenêtre rappelle l'adresse d'expédition et signale les images sans photo et les boutons sans lien.
**Limites :** pas de rotation d'image à l'angle voulu (les messageries ignorent les transformations CSS : Gmail, Outlook) ; pas de fond d'image derrière un bandeau (non fiable dans Outlook) ; une colonne ne passe pas au-dessus d'une autre sur mobile dans l'ordre inverse (elles s'empilent dans l'ordre de lecture).
**Tests :** `NewsletterEditorTest` (41 : arbre de blocs, profondeur, identifiants, plafond, rendu, texte clair sur fond sombre, modèles, compositions, `/rows`, `/templates`, expéditeur), `tests/e2e/newsletter-editor.mjs` (parcours complet dans un vrai navigateur : latence d'ajout, glisser dans une colonne, colonnes 2/3, bandeau, espace coloré, modèles, mobile, envoi).

## D-065 · En-tête des lettres : hashtag, sceau, réseaux sociaux, réglable (11 novembre 2026)

**Besoin (retour reçu, avec une maquette) :** un en-tête de lettre comme celui du site : grand bandeau bleu, hashtag « #UAZINFOS » à gauche, sceau de l'UAZ au centre, icônes de réseaux sociaux à droite ; pouvoir ajouter ces icônes, changer le hashtag, placer chaque élément et régler la taille de l'en-tête.
**Décisions :**
1. **Quatre éléments** (logo, nom de l'université, hashtag, réseaux sociaux), chacun à **gauche, au centre, à droite ou masqué**, plus la **hauteur** du bandeau (60 à 260 px), la **taille du logo** (32 à 160 px), la **couleur** de fond, un filet doré facultatif. Sur fond sombre texte et icônes sont blancs, sur fond clair ils passent au bleu nuit. Réglages dans le thème de la lettre (`header_*`, `social_*`), validés côté serveur (emplacements connus, bornes, adresses http(s) seulement, hashtag de 40 caractères sans balisage).
2. **Réseaux sociaux :** Facebook, Instagram, YouTube, LinkedIn, TikTok et site web ; une icône n'apparaît que si son adresse est renseignée ; « Reprendre les adresses du site » reprend celles des Réglages du site. Icônes en **PNG** (blanches et sombres, `public/images/newsletter/`, générées par `scripts/build-newsletter-icons.mjs`) : les messageries n'affichent pas le SVG.
3. **Sur le plan de travail :** un clic sur l'en-tête ouvre ses réglages dans l'onglet « Bloc » ; le hashtag se modifie directement sur la lettre ; l'en-tête est redessiné seul (`/header`), sans recharger la lettre. « Disposition de l'UAZ » applique la mise en page de la maquette. Tous les modèles l'utilisent (adresses du site comprises).
4. **Rien ne change pour les lettres existantes :** les clés absentes gardent l'ancien aspect (logo et nom à gauche, filet doré), les lettres envoyées sont donc identiques.
5. **Trop d'éléments dans une zone :** ils s'empilent au lieu de déborder ; les côtés ont la même largeur pour que le centre soit vraiment centré ; les icônes se serrent quand elles sont nombreuses ; sur mobile le sceau et les icônes se réduisent.
6. **Défaut corrigé au passage :** Laravel convertit les chaînes vides en `null` avant le contrôleur. Dans le thème, une valeur vide (en-tête masqué, élément masqué, filet désactivé) était donc lue comme « absente » et reprenait sa valeur d'origine : l'en-tête ne pouvait pas être désactivé. Une clé présente mais nulle est désormais une vraie valeur vide.
**Aussi :** le bouton d'un bloc aligné à droite ou au centre utilise des marges automatiques plutôt que l'attribut `align` de son tableau (qui le faisait flotter) ; `text-align` en ligne annule le centrage historique des tableaux, d'où les marges automatiques.
**Tests :** `NewsletterHeaderTest` (14), et `tests/e2e/newsletter-editor.mjs` (clic, disposition, hashtag sur la lettre, emplacements, hauteur, taille du logo, masquage qui survit au rechargement).

## D-066 · Images des lettres : jointes au message en local, JPEG pour les messageries (11 novembre 2026)

**Constat (premier vrai envoi d'essai) :** le sceau, les icônes et les photos n'apparaissaient pas chez le destinataire : les adresses d'images pointaient vers `localhost` (APP_URL local), invisible depuis l'extérieur.
**Décisions :**
1. **Images jointes (« cid: »)** : quand l'adresse du site est locale (`localhost`, `127.0.0.1`, `.test`, `.local`), chaque image hébergée par le site et présente sur le disque est jointe au message et référencée en `cid:`. Les liens et les images d'autres sites ne sont pas touchés. Réglage `NEWSLETTER_EMBED_IMAGES` : vide = automatique, `true` = toujours, `false` = jamais. En production (site public), les images restent chargées depuis le site : messages plus légers, et c'est l'usage courant des lettres envoyées en nombre.
2. **JPEG pour les lettres :** Outlook (ordinateur) n'affiche pas le WebP. Les photos de la médiathèque ont une conversion `mail` (JPEG, 1200 px, qualité 82) utilisée par les lettres ; sans elle, repli sur la version WebP `card`, puis sur l'original. Pour les photos déjà en médiathèque : `php artisan media-library:regenerate --only-missing --force` (à lancer une fois après le déploiement ; long sur un petit serveur, sans conséquence pour le site pendant ce temps).
**Tests :** `NewsletterEditorTest` (pièces jointes en local, réglage, site public, conversion JPEG et repli).

## D-067 · En-tête : cinq icônes de même taille et même écart, sans « site web » ; images jointes rangées avec le HTML (11 novembre 2026)

**Retours reçus :** (1) les images jointes apparaissaient encore en pastilles dans Gmail ; (2) l'icône « site web » est inutile dans l'en-tête ; (3) les icônes doivent avoir toutes la même taille et le même écart, Instagram servant de référence d'échelle.
**Décisions :**
1. **Réseaux de l'en-tête :** Facebook, Instagram, YouTube, LinkedIn, TikTok (les mêmes que dans les Réglages du site). L'icône « site web » est retirée (une adresse `social_website` déjà enregistrée dans une lettre est simplement ignorée).
2. **Icônes normalisées :** `scripts/build-newsletter-icons.mjs` mesure la zone réellement dessinée de chaque icône puis la met à l'échelle et la centre pour que sa plus grande dimension visible soit celle d'Instagram. Dans l'en-tête, toutes mesurent 22 px (20 px sur mobile) avec le même écart de 10 px (6 px sur mobile) : plus de réduction selon la place, les éléments s'empilent s'il n'y a pas assez de largeur.
3. **Structure du message avec images jointes (envoi en local) :** « texte brut OU (HTML + ses images) », images sans nom de fichier ni disposition « attachment ». Symfony range par défaut les images à côté du bloc texte/HTML, ce qui fait lister les images comme des pièces jointes par Gmail ; le message est donc assemblé à la main (`NewsletterMessage`). Sur un site public les images ne sont pas jointes du tout (D-066).
**Tests :** `NewsletterEditorTest` (structure du message, absence de nom de fichier), `NewsletterHeaderTest`.

## D-068 · Photos du site actuel (zurcher.edu.mg) pour les contenus sans image (12 novembre 2026)

**Constat :** côté public, les sept cartes de formation, les quatre panneaux de domaines de l'accueil, les deux projets à soutenir et quatorze pages n'avaient aucune image (silhouette de Madagascar à la place).
**Décisions :**
1. **Commande `php artisan media:import-site-images`** (`ImportSiteImages`) : télécharge 22 photos choisies sur le site actuel, les range dans la médiathèque (titre, texte alternatif, crédit « Université Adventiste Zurcher ») et les place sur les contenus listés dans la commande. Options : `--dry-run` (simulation), `--only=texte` (une seule photo), `--from=adresse`.
2. **Jamais d'écrasement :** un contenu qui a déjà une image n'est pas touché. La commande est **rejouable** : une photo déjà importée (table `import_records`, source `site-image`) n'est pas retéléchargée.
3. **Textes alternatifs fidèles à l'image** : les noms de fichiers du site actuel ne le sont pas toujours (`dortoir-r2-chantier.jpg` montre une allée du campus, `cafeteria-buffet.jpg` une cuisine). Les photos ont été contrôlées une à une.
4. **Attributions :** formations (7), domaines (4), projets (2), pages Admissions, Système LMD, Frais, Téléchargements, Vie étudiante, Cafétéria, Dortoirs, Association des étudiants, Centre de recherche, Emplois du temps, Culte d'ouverture ; l'image de partage par défaut (Réglages) reçoit la photo des diplômés.
5. **Volontairement laissé de côté :** les témoignages vidéo (marqués « TODO-CONTENU », la photo de Kerena Ranoarilalao du site actuel ne correspond à aucun d'eux), les deux articles sans lien avec une photo (offre d'emploi, discours à la chapelle), l'accueil, « À propos » et « Confidentialité » (déjà illustrés ou sans besoin).
**Déploiement :** la commande est à lancer une fois sur chaque serveur (`php artisan media:import-site-images`) : les photos vivent dans la médiathèque (base et `storage`), pas dans Git. Compter plusieurs minutes (conversions WebP/AVIF).
**Tests :** `ImportSiteImagesTest` (import, pas d'écrasement, une seule fois, simulation, site injoignable).

## D-069 · Fiches de formation et témoignage complétés d'après le site actuel (12 novembre 2026)

**Constat :** les sept fiches de Licence affichaient « TODO-CONTENU » pour la présentation et les débouchés ; l'accueil montrait trois « témoignages vidéo » provisoires à la place d'un vrai témoignage.
**Décisions :**
1. **`App\Support\FormationContent`** : pour chaque mention, ce que la fiche du site zurcher.edu.mg publie : accroche, parcours de Licence (durée, crédits, lieu), Master associé quand il existe (Communication, Études anglophones, Gestion, Informatique), débouchés. Français, anglais et malagasy tirés du site actuel ; rien n'est ajouté. Les textes malagasy restent à faire relire par un locuteur natif.
2. **Migration `2026_11_14_100000_fill_formation_presentations`** (sites déjà installés) : remplace seulement les champs encore en TODO-CONTENU ; une saisie du back-office n'est jamais écrasée ; rejouable ; remplit aussi les traductions encore vides. Le seeder (`StructureSeeder`, `ContentTranslationsSeeder`) s'appuie sur la même classe pour les nouvelles installations.
3. **Migration `2026_11_14_110000_add_student_testimonial`** : ajoute le témoignage de Kerena Ranoarilalao (texte français et anglais du site actuel ; sa photo est rattachée par `media:import-site-images`) et **désactive** (sans supprimer) les trois témoignages vidéo provisoires, qui s'affichaient avec « TODO-CONTENU » comme nom d'auteur. Pour les remettre : Témoignages, renseigner l'auteur puis activer.
4. **Non complété, faute de source :** la présentation du Centre de recherche (le site actuel écrit lui-même qu'elle « sera publiée prochainement »), les pages « Accueil » et « Soutenir » (leur contenu vient de gabarits dédiés), l'avertissement interne de la page Confidentialité. Les conditions d'admission restent en français sur les pages anglaise et malagasy (traduction à faire).
**Tests :** `FormationContentTest`.

## D-070 · Mise en production sur zurcher.edu.mg, serveur partagé (12 novembre 2026)

**Contexte :** zurcher.edu.mg sert depuis le 4 novembre un site Next.js (utilisateur `uaz`, port 3100, Nginx `uaz-site`) sur une machine qui héberge aussi le registrariat, Moodle, la bibliothèque et l'ancien WordPress (`ancien.zurcher.edu.mg`). Décision de la direction technique : remplacer ce site par le site Laravel, en connaissance du fait que ce qui a été publié sur le Next.js depuis le 4 novembre n'existe pas dans Laravel.
**Décisions :**
1. **`scripts/provision-prod.sh` (root), trois temps :** `prepare` installe le site Laravel dans `/var/www/uaz-laravel` (base MariaDB `uaz_laravel`, `.env`, file d'attente `uaz-laravel-queue`, planificateur) et l'expose seulement sur `127.0.0.1:8088` pour le tester ; `switch` fait passer zurcher.edu.mg sur Laravel (annulation automatique si `/up` ne répond pas) ; `rollback` rend le domaine au Next.js, qui n'est jamais arrêté. Rien n'est créé comme utilisateur système, la configuration PHP commune et les autres sites Nginx ne sont pas touchés.
2. **Contenu :** la base du poste de développement est recopiée dans MariaDB par `php artisan db:import-sqlite` (sans sessions, caches, messages d'essai, abonnés ni lettres ; mots de passe remplacés par des valeurs aléatoires ; le compte super-admin reprend l'adresse `ADMIN_EMAIL` avec un mot de passe généré, écrit dans `/root/.uaz-laravel/admin-login`). Les médias (photos, documents, conversions) sont envoyés tels quels. Une base déjà importée n'est jamais écrasée (fichier `storage/.data-imported`).
3. **Redirections :** les adresses du site Next.js (`/formations/communication`, `/admission/licence`, `/universite/equipe`, articles…) sont redirigées en 301 vers leurs pages équivalentes (migration `2026_11_14_120000`) ; le préfixe `/fr` est retiré par Nginx.
4. **Défaut trouvé grâce à un essai sur MySQL avant l'installation :** le nom automatique de l'index unique des envois de lettres dépassait les 64 caractères admis par MariaDB et faisait échouer `migrate` ; il porte maintenant un nom court.
5. **Courrier :** tant qu'aucun SMTP n'est renseigné dans `.env`, `MAIL_MAILER=log` : le formulaire de contact enregistre les messages dans le back-office mais n'envoie aucun courriel.
**Points d'attention :** le PHP du serveur n'a pas l'AVIF (WebP seulement ; les AVIF déjà générés sont conservés) ; MariaDB écoute sur 0.0.0.0:3306 sur cette machine (existant, à restreindre à 127.0.0.1 par l'administrateur).
**Courrier (12 novembre 2026) :** l'envoi d'essai du back-office échouait : `MAIL_HOST` était vide (le commentaire « À DÉFINIR » du modèle de `.env` avait été pris pour une valeur, donc le mode « journal » prévu n'était pas activé). Nouvelle phase **`sudo bash provision-prod.sh mail`** : serveur, port, identifiant, mot de passe (saisie masquée, jamais écrit ailleurs que dans le `.env`), adresse d'expédition et destinataire du formulaire de contact sont demandés au clavier ; la configuration est rechargée, y compris la file d'attente (elle garde la configuration en mémoire), et un message d'essai peut être envoyé.
**Défaut corrigé au passage :** `.env` était lisible par tous les utilisateurs de la machine (droits 644, fichier recréé par `sed -i`) alors qu'il contient le mot de passe de la base et la clé de l'application. Il est maintenant toujours `www-data:www-data` en 640 (`set_env` et `sync_code`).

## D-071 · Menus hebdomadaires de la cafétéria, modifiables dans le back-office (12 novembre 2026)

**Besoin :** afficher dans la section Cafétéria un tableau par semaine, que la rédaction ajoute et modifie dans le back-office ; installer les trois menus fournis (documents « MENU CAFETERIA UAZ : 1, 2 et 3 »), à compter de cette semaine ; rester dans la direction artistique du site.
**Décisions :**
1. **Données :** table `cafeteria_menus` (une ligne par semaine : premier jour unique, JSON `meals` = sept jours × `morning` / `noon` / `evening` = listes de plats, mention facultative, interrupteur « affiché »). Les jours sont déduits de la date de début. **La semaine commence le dimanche et finit le samedi** (demande de la direction) ; le tableau n'impose pourtant pas ce jour, il suit la date choisie.
2. **Menus installés par la migration `2026_11_15_100000` :** les trois menus, en trois semaines consécutives : **menu 1 = semaine du dimanche 8 novembre** (la semaine en cours le 12 novembre), menu 2 = 15 novembre, menu 3 = 22 novembre (hypothèse : « à compter de cette semaine » = menu 1 pour la semaine en cours). Les documents sont écrits du mardi au lundi ; chaque plat **garde son jour de la semaine** et le tableau est présenté du dimanche au samedi (le dimanche et le lundi d'un menu ne sont donc plus les deux derniers jours, mais les deux premiers de sa semaine). Intitulés repris tels quels (français, anglais et malgache mêlés, comme sur les documents) ; seules les fautes de frappe évidentes sont corrigées (voir `App\Support\CafeteriaMenuData`). Une semaine déjà saisie n'est jamais écrasée.
3. **Back-office (`CafeteriaMenuResource`, groupe Contenus) :** liste (semaine, période En cours / À venir / Passée, nombre de plats, interrupteur), formulaire avec un onglet par jour dont le libellé suit la date choisie, un champ par repas (un plat par ligne), unicité de la semaine, action **Dupliquer vers une autre semaine** (copie non affichée). Droits : ceux des contenus de référence (`ManagedContentPolicy`) : lecture pour tous les rôles de contenu, écriture pour l'éditeur, suppression soumise à `content.delete`.
4. **Page publique :** nouvelle section « Au menu » entre « À l'assiette » et le triptyque, dans la palette de la page (crème, terracotta, ardoise ; titre serif, onglets et tableau à angles droits comme le reste du site) ; bouton « Voir le menu de la semaine » dans le héros. On affiche la semaine en cours (jusqu'au dernier jour) et les semaines à venir (quatre au plus) ; les semaines passées, inactives ou sans aucun plat ne sont pas montrées, et la section disparaît s'il n'y en a aucune. Aujourd'hui est repéré dans le tableau.
5. **Responsive et accessibilité :** vrai `<table>` (légende, `th scope`, `aria-current="date"` sur le jour), onglets ARIA (flèches, début, fin), sous 1 024 px un seul jour à la fois (boutons de jour). **Sans JavaScript**, toutes les semaines sont empilées et le tableau défile à l'horizontale (renforcement progressif, voir `cafeteria.js`). Titres, jours et dates suivent la langue du site (anglais, malagasy) ; les plats ne sont pas traduits.
**Tests :** `CafeteriaMenuTest`, `CafeteriaMenuMigrationTest`, `CafeteriaMenuPageTest`, `CafeteriaMenuAdminTest`, et le parcours navigateur `tests/e2e/cafeteria.mjs` (onglets, clavier, jour sur mobile, sans JavaScript).
**Déploiement :** `php artisan migrate --force` (crée la table et installe les trois semaines), puis nouveau build des assets.

## D-072 · Reprise des abonnés MailPoet dans les abonnés de la lettre (12 novembre 2026)

**Besoin :** reprendre la liste d'abonnés de l'ancien site (export CSV de MailPoet, plus de 400 lignes) dans « Abonnés à la lettre » du back-office.
**Décisions :**
1. **Bouton « Importer un export MailPoet »** dans la liste des abonnés (réservé aux administrateurs, permission `users.manage` : il ajoute des personnes à une liste d'envoi), avec option « Simulation ». Même logique en ligne de commande : `php artisan newsletter:import-mailpoet fichier.csv [--dry-run]`. Classe `App\Support\MailpoetImport`. Le fichier (adresses de personnes) n'est ni enregistré sur le serveur ni versionné ; les tests n'utilisent que des adresses fictives.
2. **Le consentement prime sur la complétude de la liste :** `subscribed` → actif (date d'abonnement conservée comme « Demande du ») ; `unconfirmed` → abonné actif aussi *(version initiale : « non confirmé », sans envoi ; remplacé par D-074)* ; `unsubscribed` → désinscrit (la désinscription est conservée) ; `bounced` → ignoré. Jamais de réinscription d'une personne désinscrite sur ce site ; un abonné déjà actif n'est pas modifié ; une désinscription faite dans MailPoet est appliquée aux abonnés déjà présents.
3. **Nettoyage :** adresses mises en minuscules, espaces et « ; » finaux retirés (`nom@exemple.test ;` devient valide), adresses invalides et doublons comptés puis ignorés ; encodage (UTF-8, Windows-1252) et séparateur (virgule, point-virgule, tabulation) reconnus ; colonnes lues par leur titre.
**Point d'attention :** dans l'export fourni, environ 300 contacts portent la même date d'abonnement (2 juillet 2026, 05:27:33) et aucune date de confirmation : ils ont été ajoutés en bloc dans MailPoet, pas par leur propre demande. MailPoet les considérait « abonnés » : ils le restent ici, mais chaque lettre contient le lien de désinscription en un clic, et l'UAZ garde la responsabilité de la base légale de ces envois (voir la page Confidentialité).
**Tests :** `MailpoetImportTest` (états, dates, nettoyage, doublons, désinscrits, simulation, réimport, encodages, commande), `NewsletterSubscribersImportTest` (bouton, droits, simulation, erreurs).

## D-073 · Abonnement immédiat à la lettre d'information, sans e-mail de confirmation (12 novembre 2026)

**Demande de l'UAZ :** « mettre en marche le modal d'abonnement : en cliquant sur S'abonner, la personne est directement abonnée, sans mail de confirmation ». La fenêtre échouait en production : l'envoi de l'e-mail de confirmation n'était pas possible (aucun SMTP configuré) et la personne recevait une erreur.
**Décisions :**
1. **`POST /newsletter`** inscrit l'adresse **tout de suite** (`confirmed_at` = maintenant) : plus d'e-mail, plus de dépendance au serveur de courrier pour s'abonner. Une adresse déjà abonnée reçoit la même réponse sans changement ; une adresse « non confirmée » (reprise de MailPoet) ou **désinscrite** qui s'abonne à nouveau devient active (c'est une demande explicite de la personne).
2. **Retiré :** l'e-mail de confirmation (`NewsletterConfirmation`, son gabarit), la page et le lien signé `newsletter.confirm`, le délai de 10 minutes entre deux e-mails, les phrases correspondantes des traductions. La colonne `confirmation_sent_at` reste en base, sans usage.
3. **Fenêtre :** « Vous êtes abonné(e) » (au lieu de « Presque terminé »), le texte « Un e-mail de confirmation vous sera envoyé » est supprimé (français, anglais, malagasy).
4. **Page « Confidentialité » corrigée** (graine + migration `2026_11_16_100000` pour la page déjà en base, français et anglais) : elle affirmait « nous vous envoyons d'abord un e-mail de confirmation… personne ne peut vous inscrire à votre place », ce qui n'est plus vrai. Un texte réécrit dans le back-office n'est jamais modifié.
**Contrepartie assumée :** sans confirmation, **n'importe qui peut saisir l'adresse de quelqu'un d'autre** et l'abonner à son insu. Garde-fous conservés : validation de l'adresse, limite de 6 demandes par minute et par adresse IP, champ piège contre les robots, **lien de désinscription en un clic dans chaque lettre**, suppression d'un abonné possible dans le back-office (droit à l'effacement). Si des abus apparaissent (adresses de tiers, robots), le retour à la double confirmation demande un SMTP de production configuré (`provision-prod.sh mail`).
**Tests :** `NewsletterTest` (abonnement immédiat sans courrier, même avec un serveur de courrier hors service, adresse déjà abonnée, réinscription, non confirmé → actif, plus de lien de confirmation, fenêtre), `NewsletterPrivacyTextTest`, `tests/e2e/newsletter.mjs`.

## D-074 · Vocabulaire « newsletter » et adresses « non confirmées » reprises comme abonnées (12 novembre 2026)

**Demandes de l'UAZ :** (1) mettre directement les adresses « en attente de confirmation » en abonnés ; (2) dire **« newsletter »** dans toutes les pages, et non « lettre d'information » (en français).
**Décisions :**
1. **Import MailPoet :** les adresses `unconfirmed` deviennent des abonnés actifs, comme les `subscribed` (date d'abonnement conservée). Le compte rendu indique combien n'avaient pas confirmé dans MailPoet. Les `unsubscribed` restent désinscrits, les `bounced` sont ignorés, une personne déjà désinscrite sur le site n'est jamais réinscrite (inchangé). Les adresses déjà présentes en attente sont abonnées. Migration `2026_11_16_120000` : les adresses restées « en attente » dans la base deviennent abonnées (jamais les désinscrites). Il n'existe donc plus de chemin qui crée une adresse « en attente » ; l'état reste dans le code pour d'éventuelles anciennes lignes.
2. **Vocabulaire :** « newsletter » (féminin : « la newsletter », « une newsletter », « cette newsletter ») remplace « lettre d'information » et « lettre » quand le mot désigne la newsletter : fenêtre d'abonnement, page de désinscription, page Confidentialité, abonnements « S'abonner à la newsletter » des pages Actualités et Événements, éditeur de newsletter (panneaux, messages, titres), liste des abonnés (« Abonnés à la newsletter »), modèles (« Newsletter simple »), objet des modèles, guide du rédacteur, guide de déploiement. Les autres sens de « lettre » sont conservés (lettre de recommandation, « Arts & lettres », lettres animées des titres). Les phrases françaises servant de clés de traduction ont été renommées dans `lang/en.json` et `lang/mg.json` (traductions inchangées).
3. **Données déjà en base :** la page Confidentialité est mise à jour par la migration `2026_11_16_110000` (une saisie différente n'est pas touchée). Les newsletters déjà rédigées ne sont pas modifiées.
4. **Garde-fou :** le test `NewsletterWordingTest` échoue si « lettre d'information » réapparaît dans le code, les vues, les scripts, les traductions ou les graines, ou sur les pages publiques.
**Historique :** les décisions antérieures de ce fichier gardent leur vocabulaire d'époque (« lettre d'information »).
**Tests :** `NewsletterWordingTest`, `MailpoetImportTest` (mis à jour), `NewsletterTest`.

## D-075 · Modèles de newsletter refaits (4 styles aux couleurs de l'UAZ), parallax et mosaïque « bento » (12 novembre 2026)

**Demande de l'UAZ :** des modèles prêts à l'emploi dans le style de quatre références (vitrine avec panneau translucide et tampon rond ; récit en rangées photo/texte avec rectangle décalé derrière la photo ; magazine avec citation sur photo, bandes de couleur, rangées de trois photos et grand bouton ; affiche beige avec très grand titre et photos encadrées), **aux couleurs de l'UAZ, avec l'en-tête inchangé**, et les effets **parallax**, **mosaïque** et **bento**.
**Décisions :**
1. **Deux nouveaux blocs (rubrique « Effets »)** : **Photo en fond** (`hero`) — photo recadrée à la taille exacte posée en fond d'une cellule, avec disposition du texte (panneau à gauche, à droite, au centre, bandeau en bas, ou texte directement sur la photo assombrie), ton du panneau (bleu nuit, bleu, or, papier), opacité (100/88/72/55 %), tampon rond (« Nouveau »…), bouton, interrupteur **parallax** ; **Mosaïque** (`mosaic`) — cinq dispositions « bento » (une grande + deux, deux + une grande, trois égales, quatre en grille, une large + trois), hauteur, espacement, arrondi, cadre de couleur, couleur des cases vides, lien. Option **« ombre colorée décalée »** ajoutée au bloc Image (le rectangle derrière la photo).
2. **Contraintes du courrier :** les messageries ne recadrent pas les images et ignorent presque tout le CSS moderne. Chaque photo est donc **pré-découpée au pixel près** (`NewsletterRenderer::crop`, GD, à 2× pour les écrans nets, enregistrée sous `storage/app/public/newsletter-tiles/`), le fond passe par `background-image` + **VML pour Outlook**, le panneau translucide a une **couleur pleine de secours**, le tampon rond est carré dans Outlook (lisible), le parallax est un fond fixe (`background-attachment: fixed`) : actif dans certaines messageries (Apple Mail surtout), **ailleurs la photo reste simplement en place, rien n'est cassé**. Sur téléphone les cellules du bloc « Photo en fond » s'empilent.
3. **Modèles :** *À la une*, *Récit en rangées*, *Magazine*, *Affiche*, *Actualités du mois*, *Newsletter simple* remplacent les sept précédents (Actualités, Événement, Mot du recteur, Admissions, Galerie, Soutenir, Simple) ; modèle par défaut : *À la une*. Les newsletters déjà créées ne changent pas (le modèle n'est qu'un point de départ). Toutes les photos des modèles (blocs image, photo en fond, cases de mosaïque) sont posées avec les dernières de la médiathèque.
4. **Éditeur :** nouvelle rubrique « Effets » dans *Ajouter* ; clic sur une case vide ou double-clic sur une photo de la mosaïque ouvre la médiathèque **pour cette case** ; champ texte (tampon) ; avertissement à l'envoi si une photo de fond manque. Tout est validé côté serveur (disposition, hauteur, ton, opacité, tampon sans balise, couleurs `#rrggbb`).
**Tests :** `NewsletterEditorTest` (modèles construits sans perte, photos posées, rendu parallax/VML/tampon/ombre, repères d'édition absents du courrier envoyé, limites des champs).

**Complément (même jour) — modèles utilisables comme des blocs :** à la demande de l'UAZ, chaque modèle (*À la une*, *Récit en rangées*, *Magazine*, *Affiche*, *Actualités du mois*, *Newsletter simple*) est aussi proposé dans le panneau *Ajouter*, rubrique **« Modèles complets »**, pour être inséré dans une newsletter existante (par clic ou en le glissant). Il pose **tous ses blocs d'un coup**, à la suite du bloc sélectionné, **toujours au premier niveau** (jamais dans une colonne ou un bandeau, pour respecter la profondeur maximale), **sans l'en-tête** (il appartient à la newsletter, pas au modèle) et avec les dernières photos de la médiathèque. Refusé avec un message si la newsletter dépasserait 100 blocs. Un seul « Annuler » retire tout le modèle. Les blocs insérés prennent les couleurs et la police de la newsletter en cours.
**Tests :** `NewsletterEditorTest` (modèles et photos dans la bibliothèque de l'éditeur), `tests/e2e/newsletter-template-blocks.mjs`.

**Complément (13 novembre 2026) — panneau de l'éditeur :** la largeur du panneau de gauche est **réglable** (poignée sur son bord droit, glisser ou flèches, double-clic = taille d'origine, minimum 240 px, maximum 65 % de l'écran, retenue dans `localStorage`, désactivée sur petit écran où le panneau passe au-dessus). Les sélecteurs à plusieurs choix (`.nl-seg`) **passent à la ligne** au lieu de déborder, et l'étiquette « Texte sur la photo (photo assombrie) » est raccourcie en « Texte sur la photo ».
**Tests :** `tests/e2e/newsletter-panel.mjs`.

**Correctif (13 novembre 2026) — champ vidé :** un champ texte vidé dans l'éditeur (bouton sans libellé du modèle « Magazine », surtitre supprimé, tampon retiré) **reprenait sa valeur par défaut** à l'enregistrement : Laravel convertit les chaînes vides d'une requête JSON en `null`, et `NewsletterBlocks::normalize` lisait `null` comme « absent ». Un `null` explicite sur un champ texte vaut désormais « vidé » ; un champ réellement absent garde sa valeur par défaut.
**Parallax :** vérifié, l'instruction `background-attachment: fixed` est bien dans le courrier envoyé ; c'est la messagerie qui la reconnaît ou non (voir D-075, point 2).
**Test :** `NewsletterEditorTest` (« keeps a text field the editor emptied… »).

## D-076 · Lien « Se désinscrire » de l'envoi d'essai (13 novembre 2026)

**Constat :** dans l'e-mail d'essai (« Envoyer un test »), « Se désinscrire » n'était qu'un texte souligné suivi de « (le lien personnel est ajouté à chaque envoi) », donc un lien mort — l'essai part vers une adresse saisie à la main, qui n'est pas un abonné.
**Décisions :**
1. **L'envoi d'essai porte un vrai lien** signé, `/newsletter/desinscription-essai`, qui affiche la page « Envoi d'essai : le lien fonctionne » et **ne désinscrit personne** (aucun abonné n'est lié à cet envoi). L'en-tête `List-Unsubscribe` et la version texte l'utilisent aussi. Sans signature valide : refus (403).
2. **Inchangé pour les vrais envois :** chaque abonné reçoit son lien personnel signé `/newsletter/desinscription/{abonné}` (un clic, immédiat) et l'en-tête `List-Unsubscribe`. Test ajouté : le lien d'un vrai message désinscrit bien l'abonné.
3. **Plan de travail de l'éditeur :** la mention « (le lien personnel est ajouté à chaque envoi) » reste, puisqu'aucun abonné n'y est encore choisi.
**Point d'attention (non traité) :** une désinscription par simple ouverture du lien (GET) peut être déclenchée par un antivirus ou un filtre de messagerie qui « visite » les liens ; si des abonnés disparaissent sans l'avoir demandé, passer à une page de confirmation avec bouton (POST) et à `List-Unsubscribe-Post`.
**Tests :** `NewsletterEditorTest` (lien d'essai, absence d'effet, signature, lien réel).

## D-077 · Menu mobile : défilement bloqué par Lenis (13 novembre 2026)

**Constat :** sur petit écran (fenêtre étroite, émulation mobile), le menu plein écran ne montrait que ses premières rubriques : la suite (Ressources et pages utiles, Espaces, langues, « S'inscrire », adresse, réseaux sociaux) restait hors de l'écran sans pouvoir défiler à la molette ou au pavé tactile.
**Cause :** à l'ouverture du menu, le défilement fluide (Lenis) est suspendu (`lenis.stop()`), et Lenis **bloque alors la molette sur toute la page**, y compris dans le menu qui défile pourtant seul.
**Correctif :** attribut `data-lenis-prevent` sur le menu plein écran et sur la liste de résultats de la recherche (les deux zones qui défilent seules).
**Test :** `tests/e2e/mobile-menu-scroll.mjs` (molette, ordinateur à fenêtre étroite et téléphone, jusqu'à la dernière rubrique).

## D-078 · Retouche photo et Studio UAZ repris de « registrar », plus nets et dans le style des écrans de l'UAZ (13 novembre 2026)

**Demande de l'UAZ :** reprendre dans ce projet le « Studio UAZ » et la « Retouche photo » de l'application registrar, en mieux : des modèles de visuels dans le style des écrans de direct (marine, cyan, titres très gras, photo en fondu, « UAZ » en filigrane) ; des retouches et des exports plus nets (les exports de la page « Notre équipe » étaient mous).
**Décisions :**
1. **Deux outils plein écran dans le back-office** (menu *Site & médias*, comptes autorisés à ajouter des médias), hors navigation SPA de Filament : `/admin/retouche-photo` et `/admin/studio`.
2. **Retouche photo = l'atelier de registrar, repris tel quel** (WebGL 2, tout dans le navigateur : lumière, couleur, couleur par teinte, effets, recadrage, masques, détourage et fonds de studio, préréglage « Studio UAZ », filigrane, export, ZIP). Scripts dans `public/js/admin/retouche/`, style dans `public/css/admin/retouche-photo.css` (jetons de couleur de registrar repris dans la feuille), page `resources/views/admin/retouche/index.blade.php` (convertie du PHP de registrar), icônes Bootstrap Icons **hébergées ici** (`public/vendor/bootstrap-icons/`). Les modèles de détourage (MODNet, MediaPipe, ~45 Mo, une seule fois) et JSZip restent chargés depuis leurs CDN à la première utilisation. **Adaptations :** « Mettre en photo de profil » cherche et remplace la photo d'un **membre de l'équipe** ou d'un **témoignage** (`RetouchePhotoController`, la nouvelle photo est ajoutée à la médiathèque, l'ancienne y reste) au lieu d'un étudiant ou d'un enseignant ; la copie de l'atelier sur le serveur n'est pas reprise (l'atelier se retrouve dans le navigateur, base IndexedDB par compte).
3. **Retouches et exports plus nets** (`moteur.js`, `atelier.js`) : (a) la netteté se calcule à l'échelle d'un pixel de **sortie** (noyau gaussien 3 × 3, détail pris sur la photo brute, peau ménagée, pas de halo) au lieu d'un pixel de la source ; (b) **agrandissement bicubique** (Catmull-Rom) à la place du bilinéaire ; (c) biais de niveau de détail négatif en réduction ; (d) **netteté de sortie** à l'export (masque flou sur la luminance seule, plus la photo est réduite plus il en faut), réglable (Aucune / Légère / **Standard** / Forte) ; (e) qualité JPEG par défaut 95 ; (f) le lissage de la peau est moins fort (0,72 au lieu de 0,9) et le préréglage « Studio UAZ » passe à netteté 32 / lissage 18 : la peau garde du grain au lieu d'un rendu « plastique ». Mesuré sur une photo de l'équipe (2560 px réduite à 1200 px) : cheveux, monture et contours nettement plus définis.
4. **Studio UAZ = un éditeur de visuels dessiné en canvas 2D** (pas de navigateur sans interface sur le serveur : l'hébergement mutualisé n'en a pas), dans `public/js/admin/studio/` : `moteur.js` (le même code dessine l'aperçu et l'export ; texte qui s'ajuste tout seul puis alerte « trop long » qui bloque l'export ; photos découpées à la taille exacte du cadre par réductions successives puis affinées ; typographie française ; aucune ressource externe), `modeles.js` (les modèles, à enrichir **sans toucher à l'éditeur**), `editeur.js` (l'interface), `zip.js` (ZIP sans dépendance). Police **Inter** hébergée (`public/fonts/studio/`).
5. **Cinq modèles dans le style des écrans de l'UAZ**, chacun en **16:9 (1920 × 1080), 1:1, 4:5 et 9:16** : *Écran d'accueil* (« Bienvenue », « Culte du Sabbat », « Semaine de prière » ; 1 à 3 photos en bandes, lettres « UAZ » en filigrane), *Direct* (« commence bientôt », « en direct », « terminé » ; décor de visée, pastille, thème de l'année), *Annonce* (trois informations : quand, où, entrée), *Verset ou citation* (texte sur photo assombrie, logo « Toutes choses nouvelles »), *Félicitations* (portrait, nom en grand, mot de l'université). Le logo du thème fourni (`public/images/studio/theme-clair.png`) est une version à fond transparent, bleu nuit passé en blanc, cyan conservé, pour les fonds sombres.
6. **Fonctions de l'éditeur :** photos de la médiathèque ou envoyées depuis l'ordinateur (ajoutées à la médiathèque), cadrage (point focal, zoom) par photo, **zones couvertes par l'interface** en Story, **pont avec la Retouche photo** (« Retoucher » ouvre la photo dans l'atelier, « Renvoyer au Studio UAZ » la remet dans l'emplacement), **enregistrement automatique** sur le serveur (`studio_designs`, vignette incluse, chacun ne voit que ses visuels : liste, reprise, duplication, suppression), exports **PNG, PNG ×2, JPEG, ZIP des quatre formats**, nom de fichier `UAZ_{CODE}_{AAAA-MM-JJ}_{titre}_{format}.{ext}`, largeur du panneau réglable.
**À savoir :** les polices et les photos sont locales ; le détourage de la Retouche demande Internet à la première utilisation. Pas encore repris de registrar : le circuit de validation, l'historique des versions et le journal des exports.
**Tests :** `RetouchePhotoTest`, `StudioTest`, `tests/e2e/retouche-photo.mjs`, `tests/e2e/studio.mjs`.

## D-079 · Studio UAZ : on agit sur le visuel lui-même, et douze modèles dont des affiches pour les réseaux (13 novembre 2026)

**Demande de l'UAZ :** « qu'on puisse modifier ou supprimer un ou des éléments sur l'artboard du Studio, comme dans la newsletter », plus de modèles et de jolies affiches pour les réseaux (références : l'affiche « Students Employment Fair », la salutation « Bon Sabbat / Mirary Sabata mahafinaritra », le logo « Toutes choses nouvelles »), utilisables aussi dans les newsletters.
**Décisions :**
1. **Chaque élément dessiné est repérable.** Le moteur (`moteur.js`) tient un **registre** : chaque texte, photo ou morceau de décor porte un identifiant, un nom et les champs qu'il affiche ; le dessin renvoie ce registre avec l'image (`zones`). Les modèles (`modeles.js`) déclarent leurs éléments (`id`, `nom`, `champs`) avec `texte()` ou `groupe()`.
2. **Comme les blocs de la newsletter :** un clic sélectionne (cadre + barre d'outils flottante), on **glisse** pour déplacer, **A− / A+** pour grossir, **✎** (ou double-clic) ouvre une fenêtre avec les champs de l'élément, **Suppr** le masque (ou le supprime pour un élément ajouté), une photo du modèle se **recadre en la glissant** (molette : zoom), flèches pour ajuster au pixel (Maj : ×10), **Ctrl+D** duplique, **Ctrl+Z / Ctrl+Y** annulent et rétablissent (80 niveaux). Un élément masqué laisse sa place aux suivants et se **réaffiche** depuis l'onglet « Éléments ».
3. **Éléments ajoutés à la main** (onglet « Éléments ») : texte, pastille, rectangle, cercle, ligne, photo (de la médiathèque), sceau de l'UAZ, logo « Toutes choses nouvelles ». Position en proportion du visuel, taille en proportion de sa **largeur** : ils suivent le format quand on en change. On règle couleur, taille, graisse, alignement, majuscules, opacité, coins arrondis, ordre (devant / derrière).
4. **Enregistrement :** ce qui est masqué, déplacé (par format), agrandi et ajouté est gardé avec le visuel (`data.options`), nettoyé et borné côté serveur (40 éléments ajoutés au plus, couleurs `#rrggbb`, texte sans balise, positions et tailles bornées). Changer de modèle remet à zéro les masques, déplacements et tailles (les identifiants changent) mais **garde les éléments ajoutés**.
5. **Douze modèles** (les cinq précédents plus sept), chacun en quatre formats : *Affiche photo* (grande photo, titre qui mêle trait fin et gras sous un voile marine, d'après « Students Employment Fair »), *Salutation (Bon Sabbat)* (message du jour en deux langues séparées par un filet, thème en deux langues, sceau ; variantes « Bonne semaine », « Bonne année »), *Témoignage* (parole + portrait rond), *Programme* (jusqu'à six lignes heure / activité / lieu), *Inscriptions* (titre, date limite en pastille, filières, adresse), *Chiffres clés*, *Thème de l'année* (logo en grand + verset). Les modèles sont rangés en « Écrans » et « Affiches ».
6. **Pour les newsletters et les pages :** « Exporter → Ajouter à la médiathèque » envoie le PNG du visuel dans la médiathèque (avec un texte alternatif) ; on le choisit ensuite dans le bloc *Image* d'une newsletter ou dans une page.
**Tests :** `StudioTest` (nettoyage des options, plafond d'éléments), `tests/e2e/studio.mjs` (sélection, déplacement, suppression, annulation, ajout d'éléments, enregistrement des options, exports).

## D-080 · Studio UAZ : règles de composition et de mise en page appliquées à tous les modèles (13 novembre 2026)

**Demande de l'UAZ :** « améliorer les templates pour qu'on respecte les règles de composition et de mise en page » (et corriger des défauts d'affichage : les vignettes des modèles étaient écrasées).
**Règles appliquées dans le moteur et les douze modèles :**
1. **Une marge** (96 px en 16:9, 72 px en publication) : tous les textes sur le même bord gauche, les éléments de droite sur le même bord droit ; en-tête et pied identiques partout.
2. **Une échelle typographique** (`echelle()` : titre, sous-titre, texte, petit texte, étiquette) à fort contraste (grand titre ≈ 4 × le sous-titre) : une seule chose domine ; rien sous 16 px quand le texte est réductible.
3. **Un rythme sur la grille de 8 px** (`sp()`) : la distance entre deux éléments dit leur parenté (étiquette → titre → sous-titre).
4. **Centrage optique** (`centrer()`) : le bloc de texte se place un peu au-dessus du milieu de l'espace libre entre l'en-tête et le pied, au lieu d'être collé en haut avec un grand vide dessous ; il est mesuré d'abord sur un stylo d'essai. Les affiches photo reposent sur la marge du bas (`ancrerBas()`).
5. **Tient toujours dans sa zone :** si le bloc est trop haut (titre long, petit format), tout son texte rétrécit d'un cran à la fois (jusqu'à 35 %) avant l'alerte « trop long ».
6. **Zones de sécurité des Stories :** en 9:16, l'en-tête, la zone de texte et le pied restent entre 250 px du haut et 250 px du bas (`g.sur`, `g.zone`), l'en-tête descend ; les affiches en pleine page respectent les mêmes marges.
7. **Typographie :** retours à la ligne **équilibrés** (la dernière ligne n'est plus un mot isolé, les lignes ont des longueurs voisines), grands titres **alignés optiquement** sur la marge (leur marge intérieure est compensée), espaces insécables du français.
8. **Lisibilité sur photo :** voile marine sous le texte posé sur une photo, contraste vérifié sur les photos très claires.
9. **Équilibre photo / texte :** en portrait la photo est une bande de hauteur mesurée (plus courte quand le texte est abondant : Inscriptions, Annonce, Direct), les portraits sont cadrés au tiers supérieur.
**Affichage :** les vignettes de la liste des modèles gardent leurs proportions (cadre de hauteur fixe, image centrée) ; elles étaient forcées en 16:9 et écrasées pour les affiches en 4:5.
**Vérification :** les 12 modèles × 4 formats (48 compositions) ont été rendus et relus, sans débordement ; `StudioTest` garde les règles dans le code.
**Complément — des modèles aérés (13 novembre 2026) :** à la demande de l'UAZ (« pas trop chargés, tu peux enlever des mots »), chaque modèle part avec **moins de texte** : les éléments secondaires (étiquette, contexte, orateur, thème, compte, mot de l'université, filière, précisions des lignes du programme, appel en bas) sont **vides au départ** et marqués « (facultatif) » dans le formulaire ; la salutation n'a plus qu'une ligne de thème et le sceau (le thème en malgache, le compte et la seconde langue s'ajoutent à la demande) ; « Direct » garde deux anneaux au lieu de six formes ; « Annonce » part sans filigrane, avec des informations plus courtes. Un élément laissé vide **ne dessine plus ni trait ni espace** (l'étiquette, par exemple), et le bloc se recentre.

## D-081 · Retouche photo et Studio UAZ sur téléphone : l'aperçu reste fixe, les réglages défilent (13 novembre 2026)

**Constat :** sur écran de téléphone, l'aperçu de la photo (Retouche) était le premier élément d'une page qui défilait tout entière : en descendant dans les réglages, on ne le voyait plus. Même défaut, inversé, dans le Studio (visuel sous le panneau).
**Décision :** au-dessous de 860 px (900 px pour le Studio), la page ne défile plus : **l'aperçu occupe la moitié haute de l'écran, fixe**, et **seuls les réglages défilent** dans la moitié basse (leur propre zone, sans « rebond » sur la page). Détails : barre d'outils sur une seule ligne qui défile sur le côté (au lieu de deux lignes qui mangent l'aperçu), pellicule en bande de miniatures avec ses actions en icônes, histogramme réduit, cibles tactiles de 38 à 44 px, en-tête compact, hauteur en `dvh` (la barre d'adresse du navigateur mobile ne rogne plus le bas), fenêtre « Modifier » d'un élément du Studio posée en bas de l'écran. **Téléphone tenu en travers** (moins de 560 px de haut) : l'aperçu à gauche (56 %), les réglages à droite.
**Corrigé au passage :** la barre d'outils du Studio, une fois sur une seule ligne, élargissait la colonne de la grille et le visuel dépassait à droite (`minmax(0, 1fr)`).
**Test :** `tests/e2e/mobile.mjs` (Pixel 7 : l'aperçu ne bouge pas quand les réglages défilent, ni défilement de la page, ni débordement sur le côté, visuel du Studio dans la largeur, disposition en paysage).

## D-082 · Retouche photo : reflets de lunettes et défloutage (13 novembre 2026)

**Demande de l'UAZ :** « améliorer la suppression du reflet bleu sur les lunettes », puis « une fonctionnalité de suppression de flou si la photo est un peu floue ». La Retouche n'avait ni l'une ni l'autre.
**Décisions :**
1. **Reflets de lunettes** (curseur dans « Portrait », 0 à 100). Le reflet d'un écran dans les verres est bleu ou violet. Le détourage trouve la boîte du visage ; dans **la bande des yeux** de cette boîte (bords adoucis), les pixels dont le **bleu l'emporte sur le rouge** voient leur bleu ramené à la moyenne du rouge et du vert, et leur vert limité au rouge : le reflet devient neutre et chaud. Une première version (tout ce qui est bleuté ou pourpre vers un gris) grisait la peau rose des joues et du nez sur la photo d'équipe : abandonnée. La peau, même rosée, a plus de rouge que de bleu, elle n'est pas touchée. Le préréglage « Studio UAZ » l'applique à 70.
2. **Défloutage** (« Effets » : **Défloutage** 0 à 100 et **Taille du flou** 1 à 6 px). Un flou retire à la photo ce qu'un flou retire ; on le rajoute, à deux échelles (le rayon et sa moitié, lumière linéaire). Trois garde-fous : les petits écarts (grain, bruit) ne sont pas renforcés, les gros contours non plus (pas de halo), et le résultat reste dans la fourchette des pixels voisins. Les lectures se font au niveau le plus fin de la photo, pour que l'aperçu et l'export donnent le même résultat. La peau est ménagée. Sur une photo d'équipe floutée à dessein, la finesse mesurée passe de 2,5 à 3,7 (l'original : 3,6) ; au-delà d'une taille de flou de 3 px, la peau prend un aspect « posterisé » : on garde 2 px par défaut. Ce n'est pas une vraie déconvolution : une photo très floue ou bougée ne se récupère pas.
**Tests :** `RetouchePhotoTest` (curseurs et shader présents), vérification dans le navigateur (export avec et sans correction).

## D-083 · Retouche photo : la photo s'ouvre telle qu'elle est (13 novembre 2026)

**Constat :** à l'ouverture d'une photo (import ou glisser-déposer), tous les réglages sont à zéro, et pourtant l'aperçu n'était pas la photo d'origine : la chaîne de développement (courbe de gamma 2,2, compression des hautes lumières au-dessus de 90 %, hautes lumières écrêtées ramenées au neutre) la retouchait même sans réglage. Un blanc pur devenait gris clair, des reflets saturés devenaient des taches grises.
**Décision :** quand **aucun réglage** n'est actif (curseurs à zéro, couleur par teinte, virage, masques, filtre, fond d'origine), le moteur (`estNeutre`, uniforme `uNeutre`) renvoie la photo **telle quelle**, sans passer par la chaîne. Dès qu'un réglage change, la chaîne s'applique. Vérifié dans le navigateur : l'export d'une photo sans réglage (netteté d'export « aucune ») est **identique au pixel près** à l'original ; avec une exposition de +0,05, les hautes lumières écrêtées se ramènent au neutre (volontaire : un chemisier blanc ne doit pas virer au lilas).
**Test :** `RetouchePhotoTest`.

## D-084 · Retouche photo : une couleur vive ou pâle ne blanchit plus (13 novembre 2026)

**Constat :** sur un polo bleu vif, puis sur une chemise bleu très pâle (préréglage Studio UAZ, avec ou sans fond de studio), les parties éclairées du tissu prenaient des plaques blanc laiteux. Cause : la règle « hautes lumières écrêtées → neutre » (D-083) se déclenchait dès qu'**un seul canal** dépassait 95,5 % : le bleu du polo, saturé sur toute la zone claire, ou le vert d'une chemise pâle.
**Décision :** la règle se pilote par la **saturation de la couleur d'origine** : elle ne s'applique que si la couleur est déjà presque neutre (saturation sous 4 % : plein effet ; au-delà de 13 % : aucun). Un blanc écrêté à dominante lilas est toujours ramené au blanc ; un bleu vif, un bleu pâle, un cyan ou un jaune gardent leur couleur. Une première version (le plus faible des trois canaux doit être haut) laissait encore blanchir la chemise pâle, dont les trois canaux sont hauts : abandonnée. Vérifié sur les deux photos (plaques disparues, liserés blancs du polo toujours blancs).
**Pour une photo déjà très claire :** si une zone reste trop claire, baisser **Hautes lumières** et **Blancs** ; un canal écrêté ne se récupère pas, mais la couleur n'est plus effacée.
