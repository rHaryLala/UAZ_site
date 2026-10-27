# Plan de bascule depuis WordPress

Objectif : passer de l'ancien site (WordPress/Divi) au nouveau **sans perte de contenu, sans lien cassé et avec une
interruption minimale**. Prévoir une fenêtre de **2 à 3 heures**, hors période d'inscriptions, et un responsable unique
de la bascule.

## Principe

Le nouveau site est préparé, importé et validé **sur un serveur de préproduction** (sous-domaine protégé, `robots.txt`
fermé automatiquement hors production). Le jour J, on gèle le contenu de l'ancien site, on rejoue l'import (idempotent),
on bascule le DNS, puis on vérifie. L'ancien site reste intact et joignable (adresse IP / sous-domaine) pendant 30 jours
pour un retour arrière.

## J-30 à J-7 · Préparation

| Action | Responsable | Fait |
| --- | --- | --- |
| Serveur de production prêt (`docs/DEPLOIEMENT.md`), HTTPS sur un sous-domaine de test | technique | ☐ |
| Import de préproduction : `php artisan import:wordpress --dry-run` puis import réel | technique | ☐ |
| Relire **`docs/IMPORT-REPORT.md`** et **`docs/TODO-CONTENU.md`** : lever chaque point (alt, témoignages, équipe, frais, téléphone…) | rédaction | ☐ |
| Valider les chiffres clés, coordonnées, horaires et textes de la maquette (voir `docs/MAQUETTE.md`) | direction | ☐ |
| Remplacer les visuels de substitution par de **vraies photos** (héros, domaines, vie étudiante) | communication | ☐ |
| Documents Word (formulaires d'admission) → PDF ; yearbook allégé ou hébergé ailleurs | administration | ☐ |
| Créer les comptes (éditeurs/contributeurs) avec **2FA** ; former la rédaction (`docs/GUIDE-REDACTEUR.md`) | technique | ☐ |
| Pages légales (confidentialité, accessibilité…) rédigées et publiées | direction | ☐ |
| `php artisan demo:purge` (aucun contenu « (Démo) ») | technique | ☐ |
| Tests complets : `php artisan test`, `npm run e2e`, `node tests/e2e/a11y.mjs` | technique | ☐ |
| Baisser le **TTL DNS** de zurcher.edu.mg à 300 s (au moins 48 h avant) | technique | ☐ |

## J-1 · Contrôles

- [ ] Parcourir la **carte des redirections** : ouvrir 15 à 20 anciennes adresses (`docs/URL-MAP.csv`) sur la préproduction
      (en modifiant temporairement le fichier `hosts`) et vérifier la redirection 301 vers la bonne page.
- [ ] Vérifier le formulaire de contact de bout en bout (réception de l'e-mail).
- [ ] Vérifier mobile et bureau sur les pages principales ; Lighthouse ≥ 90 / 95 (voir `docs/QUALITE.md`).
- [ ] Sauvegarde complète de l'**ancien** site WordPress (fichiers + base) : c'est le filet de sécurité.
- [ ] Prévenir les équipes du **gel de contenu** à partir de l'heure H.

## Jour J

1. **H-0 · Gel du contenu** : plus aucune modification sur WordPress (publier une bannière « maintenance » si possible).
2. **Import final** sur le serveur de production :
   ```bash
   php artisan import:wordpress --dry-run         # relire le résumé
   php artisan import:wordpress                   # reprend les derniers articles/pages
   php artisan media-library:regenerate --only-missing
   php artisan redirects:import                   # redirections de docs/URL-MAP.csv
   ```
   Les contenus retouchés dans le nouveau back-office **ne sont pas écrasés** (ils sont listés « à relire »).
3. **Contrôle rapide** sur l'adresse de production (via `hosts`) : accueil, une actualité, une formation, contact, `/admin`.
4. **Bascule DNS** : pointer `zurcher.edu.mg` et `www` vers l'IP du nouveau serveur (enregistrements A/AAAA).
   Conserver l'ancien site sur un autre nom (ex. `ancien.zurcher.edu.mg`) tant que la période de repli court.
5. **Certificat HTTPS** : une fois le DNS propagé, `certbot --nginx` (ou Caddy) émet le certificat définitif.
6. **Vérifications DNS** : `dig zurcher.edu.mg +short`, `dig www.zurcher.edu.mg +short`, et vérifier depuis un réseau mobile
   (autre résolveur). Contrôler aussi les enregistrements **MX/SPF/DKIM** : ne pas les modifier par erreur (courriels !).

## Après la bascule

| Quand | Action |
| --- | --- |
| H+1 | Tester 20 anciennes URLs (redirections), le formulaire de contact, la recherche, le back-office ; surveiller `storage/logs` |
| H+24 | Soumettre `https://zurcher.edu.mg/sitemap.xml` à Google Search Console / Bing ; vérifier la couverture |
| J+1 à J+7 | Suivre les 404 (journaux Nginx) et ajouter les redirections manquantes dans le back-office (Site › Redirections) |
| J+7 | Remettre le TTL DNS à une valeur normale (3600 s) |
| J+30 | Si tout est stable : archiver puis arrêter l'ancien WordPress (conserver la sauvegarde) |

## Retour arrière (si problème bloquant dans les premières heures)

1. Remettre l'ancien enregistrement DNS (TTL 300 s : effet en quelques minutes).
2. Le contenu publié entre-temps sur le nouveau site reste dans sa base : le récupérer avant de reprendre.
3. Corriger, retester sur la préproduction, puis refaire la bascule.

## Points de vigilance

- **E-mails** : l'adresse du domaine peut dépendre d'enregistrements DNS que la bascule ne doit pas toucher.
- **Liens externes** vers d'anciens PDF (`/wp-content/uploads/…`) : ils sont redirigés/importés quand le PDF est dans la
  médiathèque ; les autres (Word, yearbook) sont signalés dans le rapport d'import. Prévoir une redirection manuelle si
  des sites tiers pointent vers eux.
- **Flux RSS** de l'ancien blog (`/feed/`) : redirigé vers les actualités, pas de flux équivalent (voir DECISIONS).
- **Newsletter MailPoet** : hors périmètre ; à migrer vers l'outil de l'UAZ avant l'arrêt de WordPress si elle est utilisée.
