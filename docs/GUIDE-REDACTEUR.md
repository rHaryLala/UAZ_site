# Guide du rédacteur — site de l'Université Adventiste Zurcher

Ce guide s'adresse aux personnes qui **publient et mettent à jour** le site : actualités, pages, images, événements.
Aucune connaissance technique n'est nécessaire. Comptez 15 minutes pour le lire en entier.

> **Les captures d'écran** de ce guide sont dans `docs/screenshots/` (régénérables avec `node tests/e2e/admin-shots.mjs`).

---

## 1. Se connecter

1. Ouvrez l'adresse du back-office : **`https://zurcher.edu.mg/admin`**.
2. Saisissez votre **adresse e-mail** et votre **mot de passe**, puis cliquez sur *Connexion*.

![Écran de connexion](screenshots/01-connexion.png)

**Activer la double authentification (fortement recommandé).** Cliquez sur votre nom en haut à droite → *Profil* →
*Authentification à deux facteurs* → *Configurer*. Scannez le QR code avec une application (Google Authenticator,
Microsoft Authenticator, Aegis…), saisissez le code, puis **conservez les codes de secours** dans un endroit sûr.
Ensuite, chaque connexion demandera un code à 6 chiffres.

### Qui peut faire quoi ?

| Rôle | Peut… |
| --- | --- |
| **Contributeur** | écrire des **brouillons** et modifier **ses propres brouillons** ; envoyer des images. Il ne peut pas publier ni supprimer. |
| **Éditeur** | tout ce qui touche aux contenus : publier, programmer, modifier, supprimer ; gérer la médiathèque, les menus, les réglages ; lire les messages de contact. |
| **Super-administrateur** | tout, y compris les comptes utilisateurs. |

Un contributeur voit *« Seul un éditeur peut publier »* : son contenu reste en brouillon jusqu'à validation par un éditeur.

---

## 2. Découvrir l'écran

![Tableau de bord](screenshots/02-tableau-de-bord.png)

Le **menu à gauche** est organisé en rubriques :

- **Contenus** : Pages, Actualités, Événements, Albums photo, Catégories d'actualités
- **Formations** : Formations, Facultés / départements
- **Communauté** : Équipe, Témoignages, Partenaires, Messages de contact
- **Médias** : Médiathèque
- **Site** : Réglages du site, Menus, Redirections
- **Administration** : Utilisateurs *(super-administrateur)*

Chaque liste a une **barre de recherche**, des **filtres** (statut, catégorie…) et des boutons d'action
(*Modifier*, *Aperçu*, *Supprimer*). Le bouton **Aperçu** ouvre la page telle que les visiteurs la verront — même si elle est
encore en brouillon (seuls les comptes connectés voient les brouillons).

---

## 3. Envoyer des images (la médiathèque)

Toutes les images et tous les PDF passent par la **Médiathèque** : on les envoie une fois, puis on les réutilise partout.

![Médiathèque](screenshots/05-mediatheque.png)

### Envoyer plusieurs images d'un coup

1. **Médias → Médiathèque → Envoi multiple**.
2. Déposez vos fichiers (30 au maximum à la fois).
3. Pour **chaque** fichier, décrivez l'image dans *Texte alternatif* (obligatoire).
4. Cliquez sur *Enregistrer dans la médiathèque*.

![Envoi multiple](screenshots/06-envoi-multiple.png)

### Le texte alternatif, c'est quoi ?

Une phrase courte qui **décrit l'image** pour les personnes aveugles ou malvoyantes (lecteurs d'écran) et pour Google.
Il est **obligatoire**.

| ✅ Bon | ❌ À éviter |
| --- | --- |
| « Étudiants de première année devant la bibliothèque » | « IMG_6536 » |
| « Remise des diplômes de la promotion 2025 sur la pelouse du campus » | « photo » |

Pour un PDF, indiquez son sujet : « Calendrier académique 2026-2027 ».

### Règles pratiques

- Formats acceptés : **JPEG, PNG, WebP** et **PDF**. Taille maximale : **10 Mo** par fichier.
- Pas besoin de réduire vos photos : elles sont **compressées automatiquement** (taille maxi 2 560 px) et déclinées en
  plusieurs tailles (WebP/AVIF) pour que le site reste rapide sur mobile.
- Fichiers Word, Excel, vidéos : non acceptés. Convertissez en PDF.
- Le filtre **« Sans texte alternatif »** de la médiathèque liste les images à compléter.

---

## 4. Publier une actualité (pas à pas)

1. **Contenus → Actualités → Nouvelle actualité**.

![Liste des actualités](screenshots/03-actualites-liste.png)

2. Remplissez :
   - **Titre** : clair et informatif (« Master en ligne : l'UAZ ouvre son année académique »).
   - **Extrait** : 1 à 2 phrases, affichées dans les listes et sur les réseaux sociaux.
   - **Image à la une** : cliquez sur le champ et **choisissez** une image de la médiathèque (recherche par titre), ou cliquez
     sur **« + »** pour **envoyer une nouvelle image** sans quitter la page (texte alternatif obligatoire).
   - **Texte** : l'éditeur propose gras, italique, titres (H2/H3), listes, citations et liens.
3. À droite :
   - **Catégorie** (créez-en une avec « + » si besoin) et **Auteur**.
   - **Mettre à la une** : l'article passe en tête de l'accueil.
   - **Statut** et **date de publication** (voir ci-dessous).
4. Cliquez sur **Créer**. Utilisez **Aperçu** pour vérifier le rendu.

![Formulaire d'actualité](screenshots/04-actualite-formulaire.png)

### Brouillon, publication immédiate, publication programmée

| Je veux… | Statut | Date de publication |
| --- | --- | --- |
| garder en brouillon | **Brouillon** | — |
| publier maintenant | **Publié** | laisser **vide** |
| publier plus tard | **Publié** | choisir la **date et l'heure** : l'article est « Programmé » et apparaît tout seul |

Dans la liste, un badge indique **Brouillon**, **Programmé** ou **Publié**.

### Conseils de rédaction

- Un seul sujet par article ; commencez par l'information la plus importante.
- Titres intermédiaires (H2) tous les 3-4 paragraphes : ils aident la lecture sur mobile.
- Liens : sélectionnez le texte (« conditions d'admission »), cliquez sur l'icône lien. Évitez « cliquez ici ».
- Images dans le texte : insérez-les via les **blocs** d'une page ; pour une actualité, l'image à la une suffit en général.
- Le **titre SEO** et la **description** (section « Référencement ») sont facultatifs : sans saisie, le titre et l'extrait sont utilisés.

---

## 5. Modifier une page

1. **Contenus → Pages**, cliquez sur **Modifier** à côté de la page.

![Liste des pages](screenshots/07-pages-liste.png)

2. Le contenu est composé de **blocs** que vous pouvez ajouter, déplacer (flèches) et supprimer :

| Bloc | Usage |
| --- | --- |
| **Texte** | paragraphes, titres, listes, liens |
| **Image** | une image large avec légende |
| **Galerie** | plusieurs photos avec visionneuse |
| **Citation** | citation mise en valeur (témoignage, verset…) |
| **Appel à l'action** | bandeau avec bouton (« Candidater », « Nous contacter ») |
| **Chiffres clés** | 2 à 6 chiffres avec libellé |
| **Liste de cartes** | cartes titre + texte + image + lien (programmes, services…) |

![Édition d'une page](screenshots/08-page-edition.png)

3. **Enregistrer**. Pensez à **Aperçu** avant d'annoncer la page.

Les pages « système » (À propos, Admissions, Vie étudiante…) ne peuvent pas être supprimées : modifiez leur contenu librement.
Pour créer une **sous-page**, choisissez une **page parente** (l'adresse devient `/admissions/licence`).

---

## 6. Les autres contenus

- **Événements** : titre, dates de début/fin, lieu, lien d'inscription. Ils passent automatiquement de « À venir » à « Passés ».
- **Formations** : onglets *Présentation*, *Débouchés*, *Admission* ; niveau (Licence, Master…), durée, crédits ECTS ;
  « Mettre en avant sur l'accueil » pour les cartes de la page d'accueil.
- **Équipe** : photo, fonction, e-mail (visible sur le site), ordre d'affichage par glisser-déposer. Désactivez *Affiché sur le site*
  plutôt que supprimer.
- **Témoignages** : citation et/ou vidéo YouTube ; seuls les témoignages *Affichés* apparaissent sur l'accueil.
- **Albums photo** : choisissez plusieurs photos d'un coup (« Ajouter plusieurs photos d'un coup ») puis ordonnez-les.
- **Partenaires** : logo + lien (affichés en bas de l'accueil).

---

## 7. Menus et réglages du site

**Site → Menus** : le menu principal (en-tête) et le menu du pied de page. Ajoutez un lien *vers une page du site* ou une
*adresse libre*, avec éventuellement un sous-menu.

![Menus](screenshots/09-menus.png)

**Site → Réglages du site** : nom, slogan, logo, **coordonnées**, horaires, réseaux sociaux, **titre et image de l'accueil**,
**chiffres clés** de l'accueil (ne saisissez que des chiffres officiels), pied de page.

![Réglages](screenshots/10-reglages.png)

**Site → Redirections** : si vous renommez ou supprimez une page très visitée, ajoutez une redirection (ancienne adresse → nouvelle
adresse, type 301) pour que les anciens liens continuent de fonctionner.

---

## 8. Messages reçus par le formulaire de contact

**Communauté → Messages de contact** : liste des messages (une enveloppe pleine marque les messages non lus ; un badge indique leur nombre dans le menu).
Cliquez sur **Lire** pour ouvrir un message. Vous pouvez **exporter en CSV** (compatible Excel). Les messages ne sont pas modifiables.
Supprimez ceux dont vous n'avez plus besoin : ce sont des données personnelles.

![Messages](screenshots/11-messages.png)

---

## 9. Bonnes pratiques et dépannage

- **Enregistrez** avant de quitter une page ; le navigateur prévient en cas de modification non enregistrée.
- **Je ne vois pas ma page sur le site** : vérifiez le statut (*Publié*) et la date de publication (pas dans le futur) ; videz le cache
  de votre navigateur (Ctrl+F5).
- **Mon image est refusée** : vérifiez le format (JPEG/PNG/WebP/PDF) et la taille (< 10 Mo).
- **Un lien est cassé** : ouvrez la page cible dans Pages et vérifiez son adresse ; ajoutez une redirection si besoin.
- **J'ai supprimé quelque chose par erreur** : prévenez rapidement l'administrateur, il existe des sauvegardes quotidiennes.
- **Mot de passe oublié** : « Mot de passe oublié ? » sur l'écran de connexion, ou demandez un nouveau mot de passe à l'administrateur.
- **Respect des personnes** : ne publiez ni photo ni information personnelle sans l'accord des personnes concernées.

## 10. Aide-mémoire

| Action | Où |
| --- | --- |
| Nouvelle actualité | Contenus → Actualités → Nouvelle actualité |
| Envoyer des images | Médias → Médiathèque → Envoi multiple |
| Modifier l'accueil (titre, chiffres, image) | Site → Réglages du site → Accueil |
| Modifier un texte de page | Contenus → Pages → Modifier |
| Ajouter un événement | Contenus → Événements → Nouvel événement |
| Lire les messages | Communauté → Messages de contact |
| Changer le menu | Site → Menus |

## Médiathèque : vue liste ou vue grille
La page **Médias** s'ouvre en **vue grille** : elle affiche les images en grandes vignettes (4 par ligne sur un écran standard), pratique pour retrouver
une photo. Le bouton **Vue liste** (en haut) bascule au tableau avec le poids et la date. Le choix est mémorisé pour votre session. Cliquez sur une vignette pour modifier le média ;
la recherche et les filtres (type, sans texte alternatif) fonctionnent dans les deux vues.

## Médiathèque : glisser-déposer
Au-dessus de la médiathèque, une zone **« Glissez-déposez vos images ici »** accepte des fichiers déposés depuis votre ordinateur (JPEG, PNG, WebP, PDF ; 30 maximum).
Vous pouvez aussi les lâcher n'importe où sur la page. Le formulaire d'envoi s'ouvre alors déjà rempli : il reste à saisir le **texte alternatif** de chaque fichier
(obligatoire) puis à enregistrer.

## Navigation du back-office (mode SPA)
Le back-office fonctionne en « application monopage » : un clic dans le menu ou sur un lien remplace uniquement le contenu, sans recharger toute la page
(plus rapide, plus fluide, et le survol d'un lien précharge l'écran suivant). Les boutons précédent / suivant du navigateur fonctionnent normalement.

## Tableau de bord et actions rapides
- **Tableau de bord** : quatre raccourcis (écrire une actualité, ajouter un événement, envoyer des images, lire les messages), les chiffres clés (cliquez sur une carte pour ouvrir la liste correspondante),
  les brouillons à relire et les derniers messages reçus. Il ne se rafraîchit pas tout seul : les chiffres se mettent à jour quand un contenu change.
- **Dans chaque liste** (pages, actualités, événements, formations) : **Aperçu** et **Modifier** sont à portée de clic ; le menu **⋯** propose **Dupliquer** (la copie est un brouillon dont le titre finit par « (copie) »),
  **Publier** ou **Repasser en brouillon** (avec confirmation), et **Supprimer**. Cochez plusieurs lignes pour publier ou repasser en brouillon toute la sélection d'un coup.
- Seules les personnes autorisées à publier voient ces boutons de publication ; un contributeur garde ses contenus en brouillon.
- **Votre recherche, vos filtres et votre tri sont mémorisés** pendant la session : en revenant sur une liste, elle est comme vous l'avez laissée.
- **Navigation** : quatre groupes — Contenus, Formations, Personnes & messages (messages de contact en premier, avec leur compteur), Site & médias. L'entrée Actualités affiche le nombre de brouillons en attente.

## Choisir une image (comme sur WordPress)

Dans chaque formulaire (actualité, événement, page, album…), le champ image affiche un bouton **Choisir une image**. Il ouvre la médiathèque dans une fenêtre :

- **Médiathèque** : toutes les images en grille ; la recherche filtre par titre, texte alternatif ou nom de fichier ; la grille se prolonge en descendant. Cliquez une vignette pour voir ses détails à droite, puis **Choisir cette image** (ou double-cliquez).
- **Envoyer des fichiers** : glissez ou choisissez vos fichiers, saisissez le **texte alternatif** de chacun (obligatoire), puis **Envoyer dans la médiathèque** : le fichier est ajouté et déjà sélectionné.
- Dans un album, **Ajouter plusieurs photos d'un coup** permet de cocher plusieurs images avant de valider.
- **Changer** rouvre la fenêtre, **Retirer** vide le champ.

## Médiathèque en grille : chargement progressif

En vue grille, la médiathèque n'a plus de numéros de page : 36 fichiers s'affichent d'abord, puis la suite se charge toute seule en descendant (ou avec le bouton **Charger plus**). Un compteur indique « 36 sur 186 fichier(s) affiché(s) ». Une nouvelle recherche ou un nouveau filtre repart du début. La vue liste garde sa pagination classique.

## Lettre d'information : fenêtre « S'abonner »

- Les **nouveaux visiteurs** voient une fenêtre d'abonnement quelques secondes après leur arrivée. Si elle est fermée sans abonnement, elle **revient 20 jours plus tard** sur le même navigateur ; une fois abonné, elle ne revient plus. Le bouton **S'abonner** de la barre du haut l'ouvre à tout moment.
- Chaque demande déclenche un **e-mail de confirmation** : l'abonné n'est actif qu'après clic sur le lien. Personne ne peut inscrire l'adresse d'un tiers.
- **Personnes & messages → Abonnés à la lettre** : liste des abonnés (Abonné / En attente / Désinscrit), export CSV des abonnés actifs, suppression sur demande.
- La lettre se **compose et s'envoie depuis le site** (section suivante).

## Écrire et envoyer une lettre d'information

1. **Personnes & messages → Newsletter → Nouvelle lettre.** Donnez un objet (la ligne que l'abonné voit dans sa boîte) et **choisissez un modèle** (Actualités du mois, Événement, Mot du recteur, Admissions, Galerie souvenirs, Soutenir l'UAZ, Lettre simple) : chaque carte montre la lettre entière. Les photos du modèle sont les dernières de la médiathèque ; on change tout ensuite. L'éditeur s'ouvre.
2. **Le plan de travail est la lettre elle-même.** Cliquez sur un texte pour le modifier sur place ; sélectionnez des mots pour les mettre en gras, en italique ou en faire un lien. Cliquer sur un bloc affiche sa barre d'outils (monter, descendre, dupliquer, supprimer) ; on peut aussi le glisser.
3. **Panneau de gauche :** *Ajouter* (titre, texte, image, bouton, citation, actualités du site ; mise en page : colonnes, bandeau, séparateur, espace ; compositions toutes faites : photo + texte, deux ou trois photos, chiffres clés…), *Structure* (l'arbre des blocs), *Bloc* (réglages du bloc choisi : taille, alignement, couleur, image), *Lettre* (modèle, texte d'aperçu, couleurs, police, bandeau du haut).
   - **Ajouter un bloc :** cliquez sur sa carte (il se place sous le bloc sélectionné) ou **glissez-la sur la lettre** : un trait bleu montre où elle tombera, et une colonne ou un bandeau s'entoure quand il la recevra.
   - **Colonnes et bandeaux** : posez-les, puis mettez-y n'importe quels blocs, photos comprises. Cliquez sur le fond d'une colonne pour régler les **proportions**, l'**écart** et le **nombre de colonnes** (2 ou 3 : aucun bloc n'est perdu en changeant). « ＋ Ajouter ici » apparaît dans chaque colonne ; la flèche « ↰ » de la barre d'un bloc sélectionne son conteneur. Un bandeau sombre met son texte en clair tout seul.
   - **Photo + texte** : posez la composition « Photo + texte » (ou « Texte + photo »), cliquez sur l'emplacement de la photo pour la choisir dans la médiathèque.
   - **L'en-tête** (le bandeau du haut) : cliquez dessus pour le régler. Vous choisissez pour chaque élément (logo, nom de l'université, hashtag, réseaux sociaux) **gauche, centre, droite ou masqué**, la **hauteur** du bandeau, la **taille du logo**, la couleur de fond et le filet doré. Le **hashtag** se modifie directement sur la lettre. Les **icônes de réseaux sociaux** (Facebook, Instagram, YouTube, LinkedIn, TikTok) n'apparaissent que si leur adresse est renseignée : « Reprendre les adresses du site » les remplit d'un coup. « Disposition de l'UAZ » remet la mise en page par défaut (hashtag à gauche, sceau au centre, icônes à droite).
   - **Bande de couleur** : le bloc *Espace* a une couleur ; posé entre deux blocs il fait une bande d'un bord à l'autre de la lettre.
4. **Le travail est enregistré automatiquement** (indication en haut). Les boutons ordinateur/mobile montrent le rendu ; les flèches annulent et rétablissent.
5. **Envoyer un test** à votre propre adresse avant tout : l'objet commence par [TEST].
6. **Envoyer…** : la fenêtre rappelle le nombre d'abonnés actifs ; la lettre part à chacun avec son lien de désinscription personnel. Les abonnés *en attente de confirmation* et *désinscrits* ne reçoivent rien.
7. Une lettre **envoyée est figée** : elle reste consultable (le tableau indique envois réussis et échecs) mais ne peut plus être modifiée ni supprimée. Pour la suivante, utilisez **Dupliquer** sur une lettre précédente ou créez-en une nouvelle.

> L'envoi se fait en arrière-plan, à raison d'un courrier toutes les quelques secondes : 500 abonnés demandent une vingtaine de minutes. Le statut passe à *Envoyée* quand tout est parti.

## Éditeur visuel : modifier la page elle-même

Sur la page de modification d'une page, le bouton **Éditeur visuel** ouvre la page telle que les visiteurs la verront, et on la modifie directement.

- **Les textes** : cliquez sur un titre, un paragraphe, une citation, un libellé, et écrivez. Pour le texte mis en forme, sélectionnez des mots : une barre apparaît (gras, italique, titres, listes, citation, lien).
- **Les blocs** : cliquez sur un bloc pour le sélectionner ; sa barre d'outils permet de le monter, le descendre, le dupliquer ou le supprimer. Les boutons **+** au-dessus et en dessous ajoutent un bloc à cet endroit.
- **Le panneau de gauche** : *Ajouter* (la bibliothèque de blocs : texte, image, galerie, citation, appel à l'action, chiffres clés, cartes, téléchargements), *Structure* (la liste des blocs, que l'on peut faire glisser pour changer leur ordre), *Réglages* (les champs du bloc sélectionné : images, liens, options, listes d'éléments).
- **Les écrans** : les icônes en haut permettent de voir la page sur ordinateur, tablette et téléphone.
- **Annuler / Rétablir** : Ctrl+Z et Ctrl+Y, ou les flèches en haut. Après une suppression, un message propose aussi d'annuler.
- **Le brouillon** : tout est enregistré automatiquement dans un **brouillon** ; la page visible par les visiteurs ne change pas. **Aperçu** ouvre le brouillon avec la vraie mise en page du site. **Publier** applique le brouillon à la page. **Abandonner le brouillon** revient à la version publiée.
- **Qui peut publier** : seules les personnes qui ont le droit de publier. Un contributeur garde son brouillon.

**Limite actuelle :** les pages à mise en page dessinée sur mesure (Accueil, À propos, Soutenir, Bibliothèque, Cafétéria, Dortoirs, Admission Licence et Master, Frais académiques) ne sont pas faites de blocs : l'éditeur visuel affiche une explication et renvoie au formulaire classique. Leurs textes se trouvent dans le code du site.

## Traduire un contenu (anglais et malagasy)

Le site est disponible en français, en anglais (`/en`) et en malagasy (`/mg`). Sur chaque fiche (page, formation, actualité, événement, album, équipe,
témoignage, projet, faculté, image), le bloc **Traductions** (replié, sous le contenu) propose un onglet par langue.

- Écrivez dans l'onglet de la langue : le texte français d'origine est rappelé sous chaque champ.
- **Un champ laissé vide s'affiche en français** sur le site : vous pouvez traduire progressivement.
- Les adresses (liens) ne changent pas : seule la langue du texte change.
- Pour une **page**, l'onglet contient les mêmes blocs que le contenu français : traduisez chaque bloc, dans le même ordre.
- **Réglages du site** → onglet **Traductions** : slogan, titre et sous-titre de l'accueil, libellés des chiffres clés, horaires, pied de page.
- **Images** : traduisez le texte alternatif (lu par les lecteurs d'écran) dans la fiche de l'image de la médiathèque.
- Les libellés des **menus** se traduisent dans le code (`lang/en.json`, `lang/mg.json`) : demandez-le à l'équipe technique avant d'ajouter une rubrique.
