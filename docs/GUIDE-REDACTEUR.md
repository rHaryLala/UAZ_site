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

## Newsletter : fenêtre « S'abonner »

- Les **nouveaux visiteurs** voient une fenêtre d'abonnement quelques secondes après leur arrivée. Si elle est fermée sans abonnement, elle **revient 20 jours plus tard** sur le même navigateur ; une fois abonné, elle ne revient plus. Le bouton **S'abonner** de la barre du haut l'ouvre à tout moment.
- Un clic sur **S'abonner** suffit : la personne est **inscrite immédiatement**, aucun e-mail de confirmation n'est envoyé. Chaque newsletter contient un lien de désinscription en un clic ; vous pouvez aussi supprimer un abonné depuis la liste (Personnes & messages → Abonnés à la newsletter).
- **Personnes & messages → Abonnés à la newsletter** : liste des abonnés (Abonné / En attente / Désinscrit), export CSV des abonnés actifs, suppression sur demande.
- La newsletter se **compose et s'envoie depuis le site** (section suivante).

## Écrire et envoyer une newsletter

1. **Personnes & messages → Newsletter → Nouvelle newsletter.** Donnez un objet (la ligne que l'abonné voit dans sa boîte) et **choisissez un modèle** (À la une, Récit en rangées, Magazine, Affiche, Actualités du mois, Newsletter simple) : chaque carte montre la newsletter entière. Les photos du modèle sont les dernières de la médiathèque ; on change tout ensuite. L'éditeur s'ouvre.
2. **Le plan de travail est la newsletter elle-même.** Cliquez sur un texte pour le modifier sur place ; sélectionnez des mots pour les mettre en gras, en italique ou en faire un lien. Cliquer sur un bloc affiche sa barre d'outils (monter, descendre, dupliquer, supprimer) ; on peut aussi le glisser.
3. **Panneau de gauche :** *Ajouter* (titre, texte, image, bouton, citation, actualités du site ; mise en page : colonnes, bandeau, séparateur, espace ; compositions toutes faites : photo + texte, deux ou trois photos, chiffres clés…), *Structure* (l'arbre des blocs), *Bloc* (réglages du bloc choisi : taille, alignement, couleur, image), *Newsletter* (modèle, texte d'aperçu, couleurs, police, bandeau du haut).
   - **Ajouter un bloc :** cliquez sur sa carte (il se place sous le bloc sélectionné) ou **glissez-la sur la newsletter** : un trait bleu montre où elle tombera, et une colonne ou un bandeau s'entoure quand il la recevra.
   - **Colonnes et bandeaux** : posez-les, puis mettez-y n'importe quels blocs, photos comprises. Cliquez sur le fond d'une colonne pour régler les **proportions**, l'**écart** et le **nombre de colonnes** (2 ou 3 : aucun bloc n'est perdu en changeant). « ＋ Ajouter ici » apparaît dans chaque colonne ; la flèche « ↰ » de la barre d'un bloc sélectionne son conteneur. Un bandeau sombre met son texte en clair tout seul.
   - **Photo + texte** : posez la composition « Photo + texte » (ou « Texte + photo »), cliquez sur l'emplacement de la photo pour la choisir dans la médiathèque.
   - **L'en-tête** (le bandeau du haut) : cliquez dessus pour le régler. Vous choisissez pour chaque élément (logo, nom de l'université, hashtag, réseaux sociaux) **gauche, centre, droite ou masqué**, la **hauteur** du bandeau, la **taille du logo**, la couleur de fond et le filet doré. Le **hashtag** se modifie directement sur la newsletter. Les **icônes de réseaux sociaux** (Facebook, Instagram, YouTube, LinkedIn, TikTok) n'apparaissent que si leur adresse est renseignée : « Reprendre les adresses du site » les remplit d'un coup. « Disposition de l'UAZ » remet la mise en page par défaut (hashtag à gauche, sceau au centre, icônes à droite).
   - **Largeur du panneau :** la barre grise sur son bord droit se **glisse** pour l'élargir (jusqu'aux deux tiers de l'écran) ou le réduire (minimum 240 px) ; les flèches gauche/droite la règlent au clavier, un double-clic remet la taille d'origine. La largeur choisie est retenue sur votre ordinateur.
   - **Modèles complets :** dans *Ajouter*, la rubrique « Modèles complets » propose chaque modèle (Récit en rangées, Magazine…) comme un bloc : cliquez dessus (ou glissez-le) pour l'insérer **dans la newsletter en cours**, à la suite du bloc sélectionné. Tous ses blocs arrivent d'un coup, sans l'en-tête ; il reste à remplacer les textes et les photos. « Annuler » le retire d'un seul geste.
   - **Effets (rubrique « Effets » du panneau *Ajouter*) :** *Photo en fond* pose le texte sur une grande photo (panneau translucide à gauche, à droite, au centre ou en bas, tampon rond « Nouveau », texte directement sur la photo assombrie) ; l'interrupteur **Parallax** fixe la photo pendant le défilement dans les messageries qui le permettent (Apple Mail surtout ; ailleurs la photo reste en place, sans dégât). *Mosaïque* assemble des photos de tailles différentes (style « bento ») : choisissez la disposition, l'espacement, un cadre ; cliquez sur une case pour lui donner sa photo. Le bloc *Image* peut aussi porter une **ombre colorée décalée**. Outlook ne sait pas arrondir : le tampon y est carré.
   - **Bande de couleur** : le bloc *Espace* a une couleur ; posé entre deux blocs il fait une bande d'un bord à l'autre de la newsletter.
4. **Le travail est enregistré automatiquement** (indication en haut). Les boutons ordinateur/mobile montrent le rendu ; les flèches annulent et rétablissent.
5. **Envoyer un test** à votre propre adresse avant tout : l'objet commence par [TEST].
6. **Envoyer…** : la fenêtre rappelle le nombre d'abonnés actifs ; la newsletter part à chacun avec son lien de désinscription personnel. Les abonnés *non confirmés* et *désinscrits* ne reçoivent rien.
7. Une newsletter **envoyée est figée** : elle reste consultable (le tableau indique envois réussis et échecs) mais ne peut plus être modifiée ni supprimée. Pour la suivante, utilisez **Dupliquer** sur une newsletter précédente ou créez-en une nouvelle.

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

## Menus de la cafétéria (un tableau par semaine)

Le menu de la semaine s'affiche sur la page **Cafétéria** (Vie étudiante), sous le titre « Le menu de la semaine », dans un tableau : trois repas
(matin, midi, soir) × sept jours. S'il y a plusieurs semaines, des onglets permettent de passer de l'une à l'autre ; sur téléphone, on choisit le jour.

**Où :** menu de gauche → **Contenus** → **Menus de la cafétéria**.

- **Nouveau menu** : choisissez le **premier jour de la semaine** (la semaine va du **dimanche au samedi** : prenez le dimanche). Le site propose
  d'office la semaine qui suit le dernier menu enregistré. Une seule fiche par semaine.
- **Repas de la semaine** : un onglet par jour (le nom et la date du jour sont affichés d'après la date choisie). Dans chaque case, écrivez **un plat par
  ligne**, comme sur le menu papier. Un repas laissé vide s'affiche par un tiret.
- **Mention sous le tableau** (facultative) : une courte phrase, par exemple si le menu peut changer.
- **Affiché sur le site** : décochez pour garder un menu en préparation. Seules la **semaine en cours** et les **semaines à venir** sont visibles sur le site ;
  une semaine passée disparaît toute seule, vous n'avez rien à supprimer (elle reste dans la liste). S'il n'y a aucun menu à afficher, la section n'apparaît pas.
- **Dupliquer vers une autre semaine** (menu ⋮ de la ligne) : les menus de la cafétéria tournent sur plusieurs semaines. Choisissez le nouveau premier jour ; la copie
  est **non affichée** : relisez-la, modifiez ce qui change, puis cochez « Affiché sur le site ».
- Les plats s'affichent **tels que vous les écrivez**, dans leur langue (français, anglais, malgache) : ils ne sont pas traduits ; seuls les titres et les dates du
  tableau changent avec la langue du site.
- **Voir sur le site** (en haut de la fiche) ouvre la page au niveau du tableau.

## Reprendre les abonnés de MailPoet (ancien site)

**Où :** Personnes & messages → **Abonnés à la newsletter** → bouton **Importer un export MailPoet** (réservé aux administrateurs).

1. Dans WordPress (ancien site), MailPoet → Listes → exportez la liste d'abonnés au format **CSV**.
2. Sur le nouveau site, cliquez sur **Importer un export MailPoet** et déposez le fichier. Cochez d'abord **Simulation** si vous voulez voir le résultat sans rien enregistrer.
3. Un compte rendu s'affiche : combien d'abonnés ajoutés, en attente, désinscrits, ignorés.

Ce que fait l'import (les colonnes sont reconnues par leur titre : E-mail, État global, Temps d'abonnement, Temps de confirmation) :

| État MailPoet | Résultat sur le site |
| --- | --- |
| **subscribed** (abonné) | **Abonné** actif : il reçoit les newsletters. La date d'abonnement est conservée ; à défaut de date de confirmation, la date d'abonnement en tient lieu. |
| **unconfirmed** (non confirmé) | **Abonné** actif, comme les autres : l'abonnement sur ce site est immédiat, il n'y a plus de confirmation à attendre. |
| **unsubscribed** (désinscrit) | **Désinscrit** : la désinscription est conservée. |
| **bounced** (rebond) | Ignoré : l'adresse n'existe plus ou refuse le courrier. |

Garanties : une personne **déjà désinscrite sur le nouveau site n'est jamais réinscrite** par un import ; un abonné déjà actif n'est pas modifié ; les adresses sont nettoyées (espaces, « ; » final, majuscules) et celles qui ne sont pas des adresses valides sont listées puis ignorées ; **l'import peut être refait** sans créer de doublons. Le fichier lui-même n'est conservé nulle part.

## Studio UAZ : fabriquer un visuel

**Où :** menu *Site & médias* → **Studio UAZ**. Douze modèles dans le style des écrans et des affiches de l'université (fond marine, cyan, grands titres, photo en fondu) :

| Modèle | Pour |
|---|---|
| **Écran d'accueil** | l'ouverture d'un culte, d'une semaine de prière, d'une conférence (« Bienvenue »…), avec 1, 2 ou 3 photos |
| **Direct** | « Le direct commence bientôt », « en direct », « terminé » (trois boutons de modèles rapides) |
| **Annonce** | un événement : titre, sous-titre, et trois infos (quand, où, entrée) |
| **Verset ou citation** | une parole en grand sur une photo assombrie, avec le logo « Toutes choses nouvelles » |
| **Félicitations** | un portrait, le nom en grand, un mot de l'université |
| **Affiche photo** | une grande photo avec un titre qui mêle trait fin et gras (salons, journées, concours) |
| **Salutation (Bon Sabbat)** | le message du jour en deux langues, sur une belle photo |
| **Témoignage** | une parole et un portrait rond |
| **Programme** | un horaire : jusqu'à six lignes (heure, activité, lieu) |
| **Inscriptions** | titre, date limite en pastille, filières, adresse |
| **Chiffres clés** | trois ou quatre chiffres en grand |
| **Thème de l'année** | le logo « Toutes choses nouvelles » et un verset |

**Modifier directement sur le visuel (comme dans la newsletter) :**
- **Cliquez** sur un texte, une photo ou un décor : un cadre apparaît avec une barre d'outils. **Glissez** pour le déplacer ; **A− / A+** pour le grossir ou le réduire ; **✎** (ou double-clic) pour changer son texte ; **Suppr** pour le supprimer. Une **photo** du modèle se recadre en la glissant (molette : zoom).
- **Ctrl+Z / Ctrl+Y** : annuler et rétablir. **Flèches** : ajuster d'un pixel (avec Maj : dix). **Ctrl+D** : dupliquer un élément ajouté.
- L'onglet **Éléments** liste tout ce qui est sur le visuel (on y réaffiche ce qu'on a supprimé) et permet d'**ajouter** un texte, une pastille, un rectangle, un cercle, une ligne, une photo, le sceau de l'UAZ ou le logo « Toutes choses nouvelles ».
- Exporter → **Ajouter à la médiathèque** : le visuel devient une image de la médiathèque, à insérer ensuite dans une newsletter (bloc Image) ou une page.

1. **Modèles :** cliquez sur un modèle. **Contenu :** remplissez les champs (un compteur indique la longueur maximale ; le texte se réduit tout seul s'il est long, et une alerte rouge bloque l'export s'il déborde vraiment).
2. **Photos :** *Choisir* ouvre la médiathèque (ou *Envoyer une photo* depuis votre ordinateur). *Cadrage ↔ ↕* et *Zoom* règlent ce qui reste dans le cadre. *Retoucher* ouvre la photo dans la Retouche photo ; son bouton **Renvoyer au Studio UAZ** la remet dans le visuel.
3. **Formats :** un onglet par format (16:9 pour les écrans, 1:1 et 4:5 pour les publications, 9:16 pour les stories). En Story, cochez *Zones couvertes par l'interface* : ce qui est dans les bandes rouges sera masqué par Instagram ou Facebook.
4. **Enregistrement :** automatique ; *Mes visuels* permet de reprendre, dupliquer ou supprimer vos visuels (chacun ne voit que les siens).
5. **Exporter :** *PNG* (prêt à publier), *PNG haute définition ×2*, *JPEG* (léger) ou *Tous les formats (.zip)*. Le fichier s'appelle `UAZ_ACC_2026-11-13_semaine-de-priere_16x9.png`.

Le panneau de gauche s'élargit ou se réduit en glissant sa barre grise (ou avec les flèches).

## Retouche photo

**Où :** menu *Site & médias* → **Retouche photo** (atelier façon Lightroom, tout se passe dans votre navigateur ; les photos ne quittent pas votre ordinateur tant que vous ne cliquez pas sur « Mettre en photo de profil »). Ouvrez une ou plusieurs photos (ou glissez-les), puis :
- **Studio UAZ** (bouton en haut du panneau) : blancs neutres, peau lumineuse, noirs profonds, fond blanc de studio et cadrage portrait, en un clic ; chaque réglage reste modifiable. « Studio UAZ sur toutes » l'applique à toute la pellicule.
- Lumière, couleur, couleur par teinte, effets, **recadrage** (formats prêts : 4:5, 9:16, identité 35 × 45…), **fond** (studio, couleur, flou, image, transparent), **masques** (personne, fond, cheveux, peau, vêtements, pinceau, dégradés), **filigrane**.
- **Exporter :** JPEG, PNG ou WebP, taille réelle ou réduite. **Netteté à l'export** (Standard par défaut) redonne de la finesse aux photos réduites ; laissez-la sur Standard sauf cas particulier. Avec plusieurs photos : *Tout exporter (ZIP)*.
- **Mettre en photo de profil :** cherche un membre de l'équipe (ou un témoignage) et remplace sa photo par la photo retouchée ; l'ancienne photo reste dans la médiathèque.
- Le premier détourage télécharge un modèle d'environ 45 Mo (une seule fois, connexion Internet nécessaire) ; lumière, couleur et recadrage marchent sans Internet.
