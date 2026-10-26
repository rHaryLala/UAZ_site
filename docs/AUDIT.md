# Audit du site existant — zurcher.edu.mg

Audit réalisé le 26 octobre 2026, en **lecture seule** (requêtes GET uniquement, ≥ 600 ms entre
requêtes, `robots.txt` respecté, User-Agent identifié). Aucune écriture sur le site de production.

## 1. Synthèse

| Élément | Constat |
| --- | --- |
| CMS | WordPress 7.1.3, thème **Divi** (page builder, contenu en `et_pb_*`) |
| API REST `/wp-json/wp/v2/` | **Ouverte** en lecture (posts, pages, media, users, categories, navigation). `menu-items` : 401 |
| Sitemap | `/wp-sitemap.xml` (natif WP). `/sitemap.xml` redirige (301) |
| robots.txt | Seul `/wp-admin/` est interdit (sauf `admin-ajax.php`) |
| Plugins visibles | **MailPoet** (newsletter), Divi (thème). Pas de plugin SEO ni de formulaire tiers |
| Langue | Français uniquement |
| Volume | 23 pages, 18 articles, 529 médias (~979 Mo) |

## 2. Types de contenus et volumes

| Type | Nombre | Remarque |
| --- | ---: | --- |
| Pages (`page`) | 23 | Dont 3 doublons/vides (`home`, `accueil-2`, `s-a`) |
| Articles (`post`) | 18 | Du 24/11/2025 au 20/10/2026 |
| Catégories | 1 | « Non classé » (les 18 articles) : aucune valeur éditoriale |
| Étiquettes | 0 | |
| Auteurs | 3 | `uaz` (compte générique), `taniah`, `mireille` |
| Médias (`attachment`) | 529 | Voir §4 |
| `project` (CPT Divi) | 0 | Vide |
| `et_pb_canvas`, `wp_navigation` | 0 / 1 | Menu = liste de pages auto-générée |
| Newsletter MailPoet | `mailpoet_page` (sitemap vide) | Hors périmètre |

## 3. Arborescence des pages

```
/                         Accueil (Divi, ~27 000 car. HTML ; slides, mentions, actus, vidéos YouTube)
├── a-propos/             Campus (200 ha), histoire, vision
├── academique/           Programme académique (accréditation AAA, reconnu par le MESupRES)
│   └── emplois-du-temps/ 6 PDF (un par mention)
├── programmes/           Système LMD (Licence 180 ECTS, Master…)
├── admission/            Conditions d'admission
├── licence/              Formations offertes + documents à fournir
├── master/               Conditions par mention
├── frais-academiques/    Tableau de frais (valeurs à re-valider)
├── telechargements/      Formulaires, calendriers, plans stratégiques, annuaires (12 PDF)
├── vie-etudiante/
├── bibliotheque/         Bibliothèque Paul Pichot
├── cafeteria/
├── dortoirs/
├── personnel/            Annuaire du personnel (92 images, ~200 Ko HTML) avec e-mails
├── temoignages/          3 vidéos YouTube, pas de texte
├── contactez-nous/       Formulaire Divi + coordonnées (PK 135, RN7, Antsirabe)
├── blog/                 Liste des articles (« Actualités »)
├── aumonerie/            Cours en ligne daté (16–27 févr. 2026)
├── cours-a-la-carte/     Même cours, version courte
├── s-a/                  Un lien Facebook (« Page Facebook »)
├── home/                 Page vide
└── accueil-2/            Doublon de l'accueil
```

Menu principal observé : Accueil · À propos · Académique (› Emplois du temps) · Admission ·
Vie étudiante · Actualités · Contactez-nous · Téléchargements.

Mentions (facultés/départements) citées sur l'accueil : **Sciences infirmières, Gestion,
Informatique, Études anglophones, Théologie, Droit, Communication** (Master en Communication
annoncé le 18/09/2026). Ces libellés sont la source de vérité des « mentions » ; les durées,
débouchés et frais détaillés ne sont **pas** structurés dans WordPress (à saisir : `TODO-CONTENU`).

## 4. Médias

- **529 fichiers, ~979 Mo** : 319 JPEG, 173 PNG, 1 WebP, 33 PDF, 3 documents Word.
- **0 texte alternatif renseigné** sur 529 : les alt devront être rédigés (voir `TODO-CONTENU.md`).
- 420 médias « orphelins » (non rattachés à un contenu parent) ; seuls 3 parents distincts.
- Poids : médiane ≈ 0,85 Mo ; 231 fichiers > 1 Mo ; **44 fichiers > 5 Mo** ; un PDF de 66,8 Mo
  (`yearbook-2025`) → à ne pas importer tel quel (lien externe ou compression).
- Les PDF utiles (calendriers, frais, plans stratégiques, emplois du temps, appels à
  candidature, manuel de l'étudiant, annuaires) alimentent la page Téléchargements.

## 5. Formulaires

- **Contact** (module Divi `et_pb_contact_form`) : sera remplacé par un formulaire Livewire.
- **Recherche** WordPress (`?s=`) : remplacée par `/recherche`.
- Newsletter MailPoet : pas de formulaire visible sur les pages auditées.

## 6. Réseaux sociaux repérés

Facebook (`uazsambaina`), Instagram (`adventist.university.zurcher`), LinkedIn
(`school/zurcher`), YouTube (`@uaz-universiteadventistezu6053`).

## 7. Pages à faible valeur — à ne pas migrer

| Page | Raison | Traitement |
| --- | --- | --- |
| `home` | Vide | 301 → `/` |
| `accueil-2` | Doublon de l'accueil | 301 → `/` |
| `s-a` | Un seul lien Facebook | 301 → `/vie-etudiante` |
| `aumonerie`, `cours-a-la-carte` | Cours en ligne de février 2026, périmé | 301 → `/formations` ; archivable en Actualité si souhaité |
| `blog` | Simple liste | Remplacée par `/actualites` |
| `temoignages` | 3 vidéos, aucun texte | Vidéos → modèle Témoignage |
| `academique`, `programmes` | Contenu fusionné dans Formations / Système LMD | 301 |
| Catégorie « Non classé », pages auteur, flux RSS | Aucune valeur | 301 → `/actualites` |

## 8. Points d'attention

1. **Données personnelles** : `/personnel/` publie noms, fonctions et adresses e-mail. L'import
   les reprend telles que publiées, mais l'équipe devra confirmer leur diffusion (RGPD / loi
   malgache 2014-038).
2. **Contenu Divi** : le HTML rendu contient `et_pb_*`, styles inline, `data-start/data-end`
   (artefacts d'éditeur IA). Le nettoyage (Phase 5) devra être strict et vérifié manuellement.
3. **Slugs d'articles défectueux** : `1557-2`, `https-zurcher-edu-mg-blog`,
   `https-zurcher-edu-mg-blog-2` → régénérés depuis le titre.
4. **Auteur générique** `uaz` : à rattacher à « Rédaction UAZ ».
5. **Dates** : conservées (`date` d'origine) pour les 18 articles.

## 9. Données brutes

Les réponses JSON ont été collectées dans un répertoire temporaire hors dépôt. L'importeur
(Phase 5) relit l'API en direct ; des fixtures réduites seront versionnées pour les tests.
