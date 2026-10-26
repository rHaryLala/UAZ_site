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
