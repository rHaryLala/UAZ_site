# Contenus à fournir ou à valider (`TODO-CONTENU`)

Aucune donnée factuelle n'est inventée : tout ce qui manque est marqué `TODO-CONTENU` dans le site.

## Après audit (Phase 0)

- [ ] **Textes alternatifs** : 0/529 médias en ont un. À rédiger pour chaque image publiée.
- [ ] **Formations** : durée, débouchés, conditions d'admission détaillées par mention (non structurés
      dans WordPress).
- [ ] **Frais académiques** : confirmer les montants de l'année en cours avant publication (seul un tableau
      image/PDF existe sur l'ancien site).
- [ ] **Histoire, vision, valeurs** de l'UAZ : la page « À propos » actuelle ne décrit que le campus.
- [ ] **Membres de l'équipe** : confirmer que noms, fonctions et e-mails peuvent être publiés.
- [ ] **Témoignages** : 3 vidéos YouTube sans texte (auteur, fonction, citation à saisir).
- [ ] **Partenaires** : logos et liens (ex. USN Norvège, VID University) à fournir.
- [ ] **Coordonnées et horaires** : adresse « PK 135, RN7, Antsirabe » relevée ; horaires, téléphone et e-mail
      général à confirmer.
- [ ] **Chiffres clés** de l'accueil : aucune statistique officielle publiée (étudiants, mentions, hectares…).
      Seul « plus de 200 hectares » apparaît dans la page À propos.
- [ ] **Yearbook 2025** (66,8 Mo) : fournir une version allégée ou un lien externe.
- [ ] **Images héros / vidéo** : visuels haute définition du campus.
- [ ] **Version anglaise** : prévue plus tard, non implémentée.

## Après la maquette (Phase 2)

- [ ] **Chiffres clés de l'accueil** (préremplis depuis la maquette, à valider) : fondation 1995, 7 mentions,
      180 crédits ECTS, 200+ hectares.
- [x] **Téléphone** : +261 34 44 931 07 confirmé comme numéro de l'UAZ (27 octobre 2026) ; l'autre numéro de la maquette (+261 34 76 076 98) n'est pas publié.
- [ ] **E-mail général** `office@zurcher.edu.mg`, bureau académique `registrar@zurcher.edu.mg` + téléphone
      (+261 34 46 000 08) : à valider.
- [ ] **Horaires** (lundi–jeudi 7 h 30–12 h / 13 h 30–17 h 30 ; vendredi 7 h 30–12 h) : à valider.
- [ ] **Latitude / longitude** du campus pour la carte de la page Contact.
- [ ] **Domaines** (Arts & lettres, Sciences sociales, Sciences de la santé, Sciences de l'ingénierie) :
      confirmer s'il s'agit de facultés ou de départements, et leurs responsables.
- [ ] **Parcours** de chaque mention (repris de la maquette) et **description / débouchés / conditions** de chacune
      des 7 licences : marqués `TODO-CONTENU` dans les fiches.
- [ ] **Master** : 2 ans · 120 crédits ECTS (maquette) — liste des masters ouverts (Communication annoncé le 18/09/2026).
- [ ] **Pages système** (À propos, Admissions, Vie étudiante, Licence, Master, Frais…) : contenu à saisir ou à
      importer de WordPress (phase 5).
- [ ] **Photos réelles** du campus pour le héros, les domaines de formation, la vie étudiante : les photos de la
      maquette sont des illustrations.
- [ ] **Liens de la maquette sans page** : Se connecter, Donner, Alumni, Recherche (voir `docs/MAQUETTE.md`).

## Après la phase 4

- [ ] **Pages légales** : confidentialité, accessibilité, non-discrimination, informations d'urgence (présentes dans le pied de page de la maquette) — textes à fournir.
- [ ] **Phrases des trois piliers** (Spiritus, Manus, Mens) : reformulées à partir de la maquette, à faire valider (lang/fr/home.php).
- [ ] **Résumés** des pages Cafétéria et Dortoirs (cartes « La vie à l'UAZ » de l'accueil) : seule la Bibliothèque a un texte de la maquette.
- [ ] **Vidéo d'accueil** (facultative) : non implémentée ; une image d'en-tête se règle dans Réglages du site › Accueil.
- [ ] **Carte** du campus : latitude/longitude à renseigner (Réglages › Coordonnées) pour l'aperçu OpenStreetMap.

## Après l'import réel (27 octobre 2026)

- [ ] **16 images à la une** ont un texte alternatif **provisoire** (« Illustration : titre de l'article ») : à remplacer par une vraie description (Médias › filtre « Sans texte alternatif » ne les liste pas : repérer via IMPORT-REPORT).
- [ ] **Autres images importées sans texte alternatif** (images dans les articles) : à décrire dans la médiathèque.
- [ ] **3 témoignages vidéo** importés **désactivés** : nom, qualité, citation à saisir, puis activer.
- [ ] **92 fiches d'équipe** importées telles que publiées (noms, fonctions, e-mails) : confirmer la diffusion ; catégorie administration/enseignants déduite de la fonction, à vérifier.
- [ ] **Formulaires Word** (admission Licence, Master, recommandation) : types non autorisés → à convertir en PDF et à relier dans « Téléchargements » (les liens pointent encore vers l'ancien site).
- [ ] **yearbook-2025.pdf** (66,8 Mo) et **mr-elia** (10,4 Mo) : trop volumineux, à alléger.
- [ ] **Vidéo YouTube** de l'article « ADMISSIONS 2026 – 2027 – LICENCE » et de « À propos » : remplacées par des liens, à intégrer proprement si souhaité.
- [ ] **Newsletter MailPoet** : hors périmètre.
- [ ] **Produire les variantes AVIF** : `php artisan media-library:regenerate --only-missing` (l'import les a désactivées).
- [ ] **Année de fondation** : la maquette dit 1995 (PDF, chiffres clés) et 1996 (barre du haut de la capture). Réglage : `founded_year` dans `config/uaz.php` (1995 par défaut) ; à trancher.
- [ ] **Liens de la barre du haut** sans page dédiée : « Alumni » → https://alumni-auz.api.mg/ (nouvel onglet) ; « Donner » → formulaire de contact pré-rempli, « Se connecter » → /admin (accès rédacteurs). À remplacer (menu « Barre du haut » du back-office) quand un espace étudiant, un réseau alumni et un dispositif de dons existeront.
- [ ] **Centre de recherche (ZRC)** : page `/centre-de-recherche` créée avec les seuls faits connus (directeur Dr ANDRIAMBAHOAKA Manda, secrétaire Irina Ho Liantsoa, d'après la page « Personnel » du site actuel). À fournir : nom officiel et sigle (« Zurcher Research Center » / ZRC non retrouvés en ligne), mission, axes, projets, publications, contact. Le lien « Recherche » du menu est devenu « Centre de recherche » ; la recherche du site reste sur la loupe.
- [ ] **Citations et messages de l'équipe** : la fiche agrandie de la page Équipe affiche « Citation » et « Message aux lecteurs » (back-office, fiche de chaque membre) ; vides pour l'instant, la fiche indique « Le message de … sera bientôt publié ». À recueillir auprès des intéressés (validation par chacun).
- [ ] **Page Téléchargements** (bloc « Liste de téléchargements », modifiable dans le back-office) : contenus à corriger, repris tels quels du site WordPress — « Formulaire de recommandation » a la description des listes de frais ; « Calendrier Académique 2026 - 2027 » pointe vers le fichier 2027-2028 (même fichier que l'entrée suivante) et sa description cite 2027-2028 ; « Bulletin Académique 2027 - 2030 » à vérifier. Les formulaires Word et le yearbook pointent encore vers l'ancien site (voir plus haut). Commande de conversion : `php artisan pages:downloads-list`.
- [ ] **Page « Soutenir l'UAZ »** (`/soutenir`, lien « Donner ») : deux projets repris du document « Appel aux dons - UAZ » (grand dortoir pour les garçons, 275 000 € ; logements pour le personnel, 160 000 €) et ses contacts (téléphones et e-mails dans `config/uaz.php`). **À fournir** : les photos de l'incendie du 9 octobre 2023 et le plan du dortoir (image du projet et présentation détaillée, back-office : Communauté → Projets à soutenir), le détail du second projet (plan, nombre de logements), et la photo d'en-tête en production. **Don en ligne** : non branché (`UAZ_DONATION_URL`) ; bénéficiaire (UAZ ou fondation), moyens de paiement et reçus à décider. Le centre médical de Sambaina, porté par la Fondation Zurcher Madagascar et déjà construit, n'est volontairement pas présenté ici.
- [ ] **Phrase du héros de l'accueil** (« Votre futur commence ici ! » / « Your future starts here! » / « Eto no manomboka ny hoavanao! ») : faire relire la version **malgache** par un locuteur (textes dans `lang/fr/home.php`, clés `note`, `note_en`, `note_mg`).
- [x] **Barre du haut « Étudiant »** : liste à choix vers Registrar (https://registrar.zurcher.edu.mg) et Moodle (https://moodle.zurcher.edu.mg), nouvel onglet. « Se connecter » (→ /admin, accès rédacteurs) a été retiré de la barre du haut ; la pastille dorée « Se connecter » ouvre une fenêtre de choix (Registrar ou Moodle).
- [ ] **Titre « Votre futur commence ici. »** (accueil) en trois langues : « Your future starts here. » et « Eto no manomboka ny hoavanao. » — la version **malgache** est à faire relire par un locuteur (`lang/fr/home.php`, clés `lines_en` et `lines_mg`).

## Page « Confidentialité et cookies » (brouillon, à valider avant publication)

La page existe en **brouillon** (Pages → « Confidentialité et cookies ») ; le lien du pied de page apparaît dès qu'elle est publiée.

- [ ] **Durée de conservation** des messages du formulaire de contact : à fixer par l'UAZ.
- [ ] **Responsable des données** : confirmer l'adresse `office@zurcher.edu.mg` et le service concerné.
- [ ] **Cadre légal** (loi malgache sur la protection des données personnelles) : à faire valider par la direction ou un juriste, puis compléter la rubrique « Vos droits ».
- [ ] **Journaux de l'hébergeur** (adresses IP des visites) : à préciser selon l'hébergeur retenu.
- [ ] Retirer les mentions `TODO-CONTENU` du texte avant de publier.
- À relire si le site ajoute des statistiques, une vidéo YouTube intégrée ou une carte Google : un bandeau de consentement deviendrait alors nécessaire.

## Lettre d'information

- [ ] **Texte de la fenêtre** (« Restez proche de l'UAZ ») : à valider par l'UAZ.
- [ ] **Outil d'envoi** de la lettre (Brevo, Mailchimp…) ou envoi depuis le site : à décider ; contenu et fréquence à définir.
- [ ] **SMTP de production** : sans lui, aucun e-mail de confirmation ne part (`MAIL_*` dans `.env`).
- [ ] Page « Confidentialité et cookies » : durée de conservation après désinscription (`TODO-CONTENU` dans la page).

## Page Bibliothèque

- [ ] **Image de couverture** (back-office → Pages → Bibliothèque → « Image ») : choisir la photo de la salle de lecture (« Bibliothèque de l'UAZ »). Sans elle, la scène d'ouverture et le panorama reprennent la photo de l'enseigne. À refaire en production (les identifiants d'images diffèrent).
- [ ] **Horaires** : recopiés de l'affiche (non datée). Confirmer qu'ils sont à jour et **préciser le samedi** (absent de l'affiche).
- [ ] **Adresse** : la page importée indique « Vohitsoa – PK 136 RN7 – BP 325 » alors que le pied de page indique « PK 135 » : à harmoniser.
- [ ] **Photos supplémentaires** (rayonnages, espace enfants, postes de lecture…) : le panorama et le rayonnage gagneraient à des vues variées.
- [ ] **Site de la bibliothèque (`library.zurcher.edu.mg`)** : lié depuis la page Bibliothèque (section « En ligne » avec une capture d'écran, `public/images/library-site-*.webp`, à refaire si le site change, et coordonnées). Il interdit son affichage dans une autre page (`X-Frame-Options`), donc simple lien en nouvel onglet. Son contenu n'étant pas visible sans JavaScript, le texte de la section reste volontairement neutre : à préciser (catalogue, ressources…) quand l'UAZ le décrira.

- [ ] **Vidéo de la Bibliothèque** : fichiers `public/videos/library-720.mp4`, `library-360.mp4` et `library-poster.webp` à copier sur le serveur (les .mp4 ne sont pas dans Git). Source : `DJI_20260819095635_0097_D.MP4` (33 s), seules les 22 premières secondes sont utilisées. L'enseigne de la bibliothèque (photo) n'est plus utilisée dans la page.

## Page Cafétéria

- [ ] **Horaires, menu et contact de la cafétéria** : absents du texte importé, donc absents de la page. À fournir si l'UAZ souhaite les afficher.
- [ ] **Textes alternatifs** des trois photos : rédigés dans la page (service au buffet, cuisine, équipe) ; à vérifier dans la médiathèque (les champs « texte alternatif » des images importées sont vides).
- [ ] **Nom de l'équipe et photos supplémentaires** (salle, plats) si l'UAZ veut enrichir le triptyque.
