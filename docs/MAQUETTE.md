# Maquette de l'accueil (fournie par l'équipe UAZ)

Source : « Université Adventiste Zurcher homepage (1).pdf », reçue le 26 octobre 2026, **inachevée** selon son auteur.
C'est la référence de composition pour l'accueil (phases 3 et 4). Les informations factuelles qu'elle contient
viennent de l'équipe UAZ : elles sont reprises ici pour pré-remplir les réglages, mais restent à valider
(voir `TODO-CONTENU.md`). Les photos de la maquette ressemblent à des images d'illustration : elles ne sont
**pas** réutilisées, il faut des photos réelles du campus.

## Ordre des sections (de haut en bas)

1. **En-tête** : barre secondaire (Étudiants · Notre équipe · Alumni · Donner · Se connecter), logo, menu
   (À propos, Formations, Admissions, Vie estudiantine, Recherche), bouton « S'inscrire ».
2. **Héros** plein écran, photo du campus : surtitre « Spiritus · Manus · Mens — Fondée en 1995 », titre
   « Préparer aujourd'hui les leaders de demain », paragraphe, 2 boutons (« Découvrir l'UAZ », « Soutenir l'UAZ » depuis le 5 novembre 2026, ex-« Planifier votre visite » : voir D-061),
   mention « Admission après baccalauréat selon mention et critères de chaque filière ».
3. **La différence UAZ** : titre serif, paragraphe, lien « Mieux nous connaître », 4 chiffres clés.
4. **Formations** : titre « Une formation. Un avenir. Une vision. », grille 2×2 de 4 cartes photo par domaine
   (Arts & lettres, Sciences sociales, Sciences de la santé, Sciences de l'ingénierie) avec leurs mentions, puis
   bandeau « Licence · Master · Formations professionnelles et continues ».
5. **Admissions & aides financières** : bandeau marine, photo, carte flottante « Découvrez le campus ».
6. **La vie à l'UAZ** : grande photo + 3 cartes (activités, accompagnement, bibliothèque/laboratoires).
7. **Recherche & découverte / Apprentissage pratique** : section photo plein cadre.
8. **Témoignage** : citation sur fond marine (« En leurs propres mots »).
9. **Actualités** : 3 cartes (catégorie, date, titre, extrait, « Lire l'article »).
10. **Ressources pratiques** : 3 lignes numérotées (Bibliothèque, Emplois du temps, Téléchargements).
11. **Appel à l'action final** (fond marine) puis **pied de page** (4 colonnes : identité, ressources, contact, liens utiles).

Style : fond clair/crème, titres serif, bleu marine profond, accents or, boutons or pleins et contours,
petits surtitres en capitales espacées. Cohérent avec le logo ; le « triangle » et la silhouette de Madagascar
(demandés dans le brief) ne figurent pas encore dans la maquette : ils seront ajoutés en phase 3.

## Données factuelles à valider

| Donnée | Valeur dans la maquette | Statut |
| --- | --- | --- |
| Année de fondation | 1995 | à valider |
| Mentions de licence | 7 (Théologie, Communication, Études anglophones, Gestion, Droit, Sciences infirmières, Informatique) | cohérent avec le site actuel |
| Licence | 3 ans · 180 crédits ECTS | cohérent avec `/programmes/` |
| Master | 2 ans · 120 crédits ECTS | à valider |
| Campus | « 200+ » (hectares) | cohérent avec `/a-propos/` (« plus de 200 hectares ») |
| Parcours par mention | ex. Théologie (Pastorale · Biblique), Gestion (Management des affaires · Finance et comptabilité), Droit (Public · Privé), Sciences infirmières (Infirmerie générale · Maïeutique), Informatique (Système d'information et génie logiciel · Maintenance et administration de réseau), Communication (Entreprise · Média et Journalisme), Études anglophones (Traduction et interprétation · Socio-linguistique) | à valider |
| E-mail général | office@zurcher.edu.mg | à valider |
| Téléphone | **deux numéros différents** : +261 34 44 931 07 (en-tête du pied) et +261 34 76 076 98 (colonne contact) | **à trancher** |
| Bureau académique | registrar@zurcher.edu.mg · +261 34 46 000 08 | à valider |
| Horaires | lundi–jeudi 7 h 30–12 h et 13 h 30–17 h 30 ; vendredi 7 h 30–12 h | à valider |
| Master en ligne | lancement officiel le lundi 5 octobre 2026 à 17 h | issu d'une actualité réelle |

## Écarts avec le brief et le site actuel (à arbitrer)

- Liens **« S'inscrire », « Se connecter », « Donner », « Alumni », « Recherche »** : aucune page correspondante
  n'existe sur le site actuel ni dans le périmètre du brief (pas d'espace étudiant, pas de dons). Proposition :
  « S'inscrire » → `/admissions`, « Recherche » → `/recherche`, les autres **non implémentés** tant que leur contenu
  n'est pas défini.
- Libellé « **Vie estudiantine** » dans le menu, « **Vie étudiante** » ailleurs : on retient « Vie étudiante »
  (forme du site actuel et de la maquette elle-même).
- Section **« Recherche & découverte »** : l'UAZ n'a pas de contenu recherche publié ; la section est traitée
  comme « Apprentissage pratique » (projets, stages) tant qu'aucune page de recherche n'existe.
- Le brief demande un carrousel de **témoignages**, des **partenaires** et des **chiffres animés** : la maquette
  n'a qu'un témoignage et pas de partenaires ; on garde les sections du brief, masquées si vides.
