#!/usr/bin/env bash
# Mise à jour du site sur le serveur (à lancer depuis le dossier du projet, en tant qu'utilisateur « deploy »).
#
#   1. Sur votre poste ou en CI :  npm ci && npm run build   puis envoyer public/build/ sur le serveur (rsync).
#      Node n'est PAS installé en production : seul le résultat du build est nécessaire.
#   2. Sur le serveur :            scripts/deploy.sh
#
# Le site passe en maintenance le temps des migrations ; en cas d'échec il y reste (voir docs/DEPLOIEMENT.md § Retour arrière).
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

[ -d public/build ] || { echo "public/build manquant : envoyez d'abord le build des assets (npm run build)." >&2; exit 1; }

echo "→ Sauvegarde préalable"
scripts/backup.sh

echo "→ Maintenance"
php artisan down --retry=60 --refresh=15

echo "→ Code et dépendances"
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Base de données"
php artisan migrate --force --no-interaction

echo "→ Caches"
php artisan storage:link --no-interaction 2>/dev/null || true
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize 2>/dev/null || true
php artisan queue:restart

echo "→ Remise en ligne"
php artisan up

echo "Terminé : $(git rev-parse --short HEAD)"
