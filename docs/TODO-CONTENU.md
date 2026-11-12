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
- [x] **Version anglaise et malagasy** : mise en place (docs/I18N.md). Reste à faire, voir « Traductions » plus bas.

## Après la maquette (Phase 2)

- [ ] **Chiffres clés de l'accueil** (préremplis depuis la maquette, à valider) : fondation 1995, 7 mentions,
      180 crédits ECTS, 200+ hectares.
- [x] **Téléphone** : +261 34 44 931 07 confirmé comme numéro de l'UAZ (27 octobre 2026) ; l'autre numéro de la maquette (+261 34 76 076 98) n'est pas publié.
- [ ] **E-mail général** `office@zurcher.edu.mg`, bureau académique `registrar@zurcher.edu.mg` + téléphone
      (+261 34 46 000 08) : à valider.
- [x] **Horaires** (lundi–jeudi 7 h 30–12 h / 13 h 30–17 h 30 ; vendredi 7 h 30–12 h) : identiques sur le site actuel (accueil et « Contactez-nous », consulté le 10 novembre 2026). Année de fondation **1995** également confirmée par la page « À propos » du site actuel.
- [ ] **Description et débouchés des formations** : le site actuel (pages Programmes, Académique, Licence, Master) n'en publie **aucun** ; seuls existent les intitulés, parcours, durées (3 ans / 180 crédits ; 2 ans / 120 crédits) et les conditions d'admission. Les conditions d'admission des licences sont reprises dans les fiches (migration `2026_11_10_100000`, depuis `config/admissions.php`) ; description et débouchés restent à fournir par l'UAZ.
- [ ] **Fiches Master** : le site actuel liste Gestion (Administration des affaires ; Finance et comptabilité), Informatique (Systèmes d'information et génie logiciel ; Administration système et réseaux) et Études anglophones (LEAP ; LIAP), sans description. Non créées (voir `config/lmd.php`).
- [ ] **Importation WordPress** : `zurcher.edu.mg` redirige désormais vers `ancien.zurcher.edu.mg` ; si l'import est relancé, définir `WORDPRESS_URL=https://ancien.zurcher.edu.mg`.
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
- [ ] **Centre de recherche (ZRC)** : page `/centre-de-recherche` immersive (D-048), bâtie sur les seuls faits connus (directeur Dr ANDRIAMBAHOAKA Manda, secrétaire Irina Ho Liantsoa, d'après la page « Personnel » du site actuel ; phrase de présentation des formations du site actuel). Les sections sans donnée disparaissent. À fournir (à saisir en blocs dans le back-office, ils s'affichent alors en fin de page) : nom officiel et sigle (« Zurcher Research Center » / ZRC non retrouvés en ligne), mission, axes, projets, publications, contact. Le lien « Recherche » du menu est devenu « Centre de recherche » ; la recherche du site reste sur la loupe.
- [ ] **Citation du recteur** : « Préparer les leaders de demain est une noble et importante mission. » (extrait du « Mot du recteur » de l'accueil du site actuel) ajoutée à la fiche du Dr KANCEL Daniel par la migration `2026_11_10_100000` ; à faire valider par l'intéressé, son message complet reste à recueillir.
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
- [ ] **SMTP de production** : sans lui, aucun courriel ne part (notification du formulaire de contact, lettres d'information, envoi d'essai) (`sudo bash ~/uaz-deploy/provision-prod.sh mail` sur le serveur). L'abonnement à la lettre, lui, n'en dépend plus.
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

## Page Dortoirs

- [ ] **Intitulés « Open House » et « MJA »** : ce sont les seuls libellés du texte importé pour deux publications Facebook ; préciser ce qu'elles montrent (et le sens de « MJA ») si l'UAZ le souhaite.
- [ ] **Avancement du projet R+2** : la page reprend « le rez-de-chaussée est en cours d'achèvement » ; à mettre à jour quand l'état change (champ du texte de la page).
- [ ] **Plan réel** du rez-de-chaussée (agencement des chambres, de la réception et de la salle à manger) pour remplacer le schéma illustratif.
- [ ] **Photos** : intérieurs des chambres (non fournis), vues du chantier R+2 ; les trois photos actuelles montrent les extérieurs.
- [ ] **Textes alternatifs** des trois photos : rédigés dans la page ; à vérifier dans la médiathèque (champs vides).

- [ ] **Photos de chambres en production** : ajouter les six photos dans la médiathèque (texte alternatif obligatoire), puis les insérer à la suite des trois photos du texte de la page « Dortoirs », dans cet ordre : chambre (héros), 2 photos des garçons, 3 photos des filles. Les identifiants diffèrent de ceux du développement.
- [ ] **Consentement à l'image** : six photos montrent des étudiants et étudiantes identifiables (plusieurs sont probablement mineurs ou jeunes adultes) : confirmer l'autorisation de publication avant la mise en ligne.

## Pages Admission Licence et Master

- [ ] **Chaque année** : mettre à jour `config/admissions.php` (année, dates, liste des pièces, frais de dossier du Master) et l'affiche officielle (figure du texte de la page). Au 1er novembre 2026, seules les dates du 2nd semestre 2027 sont à venir : les autres s'affichent « Passé ».
- [ ] **Écart Master** : le texte du site demande « Lettres de recommandation (2) », l'affiche 2026-2027 ne les liste pas (elle ajoute une lettre de demande d'admission et la preuve de paiement). La page suit l'affiche ; confirmer.
- [ ] **Écart Licence** : le texte du site et l'affiche diffèrent légèrement (certificat de bonne santé scanné, visa d'études pour les étrangers, « test de niveau d'informatique » pour la Théologie). La page suit l'affiche pour le dossier et le texte pour les matières et séries de Bac ; confirmer.
- [ ] **Éducation** : citée dans les matières de test et les séries de Bac, mais sans parcours ni fiche de formation sur le site : à compléter ou à retirer.
- [ ] **Master** : durée et crédits non fournis (aucune fiche de formation Master dans le site) ; fiches des mentions de Master à créer pour lier les cartes.
- [ ] **Photo du Master** : recadrage de l'affiche officielle (faute de photo haute définition) ; la remplacer par une photo de remise de diplômes de Master (champ « Image » de la page).
- [ ] **Médias en production** : l'affiche et la photo de couverture de chaque page (Licence : « Diplômés de l'UAZ en toge et étole jaune » ; Master : « Diplômés du Master ») sont à reprendre dans la médiathèque et à choisir comme image de la page.

## Page Frais académiques

- [ ] **Chaque année** : remplacer les tableaux (images de la page) **et** `config/fees.php` ; le test `FeesPageTest` refuse les totaux qui ne correspondent pas aux lignes. Année affichée : `fees.year`.
- [ ] **Coordonnées de paiement du Master (MVola 038 53 849 49, BMOI 00004 00006 070112 001 28 32)** : transcrites de l'affiche M1 ; **à relire par la Finance avant mise en ligne**, une erreur redirigerait des paiements.
- [ ] **Définition d'« interne »** : la page dit « l'interne ajoute le dortoir et la cantine du campus », déduit des remarques « Pour les internes » des tableaux ; à confirmer (ou à reformuler).
- [ ] **Licence : plans de paiement** : aucun plan n'est publié (seul le Master en a) ; la page renvoie vers la Finance. À compléter si un échéancier existe.
- [ ] **Master M2** : seul le second semestre figure sur le tableau (étudiants sortants) ; fournir le premier semestre si besoin.
- [ ] **« Remise à niveau »** : colonne reprise telle quelle des tableaux ; ajouter une phrase d'explication si l'UAZ le souhaite.
- [ ] **Tableaux Licence** : ceux de Sciences infirmières et des autres départements ne diffèrent que par les laboratoires, le dortoir et la cantine ; les écarts de frais généraux entre semestres (213 000 / 228 000 / 168 000 selon le département et le semestre) sont ceux des tableaux.
- [ ] **Texte alternatif des six tableaux** (médiathèque) : champs vides ; la page les décrit en texte.
- [ ] **Médias en production** : reprendre les six images dans la médiathèque, dans cet ordre, à la suite du texte de la page.

## Traductions (anglais et malagasy)

- [ ] **Faire relire tout le malagasy par un locuteur natif** avant publication : `lang/mg.json`, `lang/mg/*.php` (accueil, À propos,
      Soutenir, validation), traductions malagasy de `database/seeders/ContentTranslationsSeeder.php`. Les termes d'institution
      (Oniversite Advantista Zurcher, Fiangonana Advantista Mitandrina ny Andro Fahafito, Licence/Master, « saram-pianarana »…) sont à confirmer
      avec l'UAZ ; un doute sur un mot est préférable à un faux ami.
- [ ] **Faire relire l'anglais** (ton, termes : « Bachelor's (Licence) », « boarding / day student », « field of study »).
- [ ] **Traduire le contenu importé** dans le back-office (onglet « Traductions » de chaque fiche) : actualités, événements, albums, équipe
      (fonctions, messages), témoignages, descriptions des formations, textes alternatifs des images. Un contenu non traduit s'affiche en français.
- [ ] **Page « Confidentialité et cookies »** : texte anglais prêt (seeder), texte malagasy à rédiger et à faire valider avec la version française.
- [ ] **Pages sur mesure** (À propos, Soutenir, Bibliothèque, Cafétéria, Dortoirs, Admissions, Frais) : textes traduits dans le code ; tout changement
      de texte français doit être répercuté dans `lang/en.json` et `lang/mg.json` (le test de traductions le rappelle).

- [ ] **Page « Système LMD »** : les chiffres et mentions de la page sur mesure viennent de `config/lmd.php` (copie du texte du site actuel : 3 ans / 6 semestres / 180 crédits ECTS, 2 ans / 120 crédits / 300 depuis le Bac, sept mentions de Licence, trois de Master). À mettre à jour si l'UAZ modifie ce texte. Le **Doctorat** n'a ni durée ni crédits publiés : à fournir si l'UAZ veut les afficher. Malagasy de la page à faire relire.

- [ ] **Page « Association des étudiants »** : seule la section 3.3 du Manuel de l'étudiant est reprise (rôle, officiants élus, équipe d'au plus 15 noms, entretien avec le VRAE, engagement envers les projets du VRAE). À fournir si l'UAZ le souhaite : la section 3.4 (activités de l'année), les noms des officiants de l'année, les dates de l'appel à candidature et du scrutin, les photos de l'Association. Traductions anglaise et malagasy dans `lang/{en,mg}/association.php` : le malagasy est à faire relire par un locuteur natif.
