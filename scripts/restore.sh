#!/usr/bin/env bash
# Restaure une sauvegarde créée par scripts/backup.sh.
#
# Usage : scripts/restore.sh db_AAAA-MM-JJ_HHMMSS.sql.gz media_AAAA-MM-JJ_HHMMSS.tar.gz
#
# ATTENTION : écrase la base et le dossier des médias actuels. Le script demande une confirmation
# et met le site en maintenance pendant l'opération.
set -euo pipefail

[ "$#" -eq 2 ] || { echo "Usage : $0 <dump-base.gz> <archive-medias.tar.gz>" >&2; exit 1; }

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_FILE="$1"; MEDIA_FILE="$2"
env_value() { { grep -E "^$1=" "$APP_DIR/.env" || true; } | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

gzip -t "$DB_FILE" && gzip -t "$MEDIA_FILE"

read -r -p "Remplacer la base « $(env_value DB_DATABASE) » et les médias par cette sauvegarde ? (oui/non) " answer
[ "$answer" = "oui" ] || { echo "Annulé."; exit 1; }

cd "$APP_DIR"
php artisan down --retry=60 || true

case "$(env_value DB_CONNECTION)" in
    mysql|mariadb)
        gunzip -c "$DB_FILE" | MYSQL_PWD="$(env_value DB_PASSWORD)" mysql \
            --host="$(env_value DB_HOST)" --port="$(env_value DB_PORT)" --user="$(env_value DB_USERNAME)" "$(env_value DB_DATABASE)"
        ;;
    sqlite)
        gunzip -c "$DB_FILE" > "$(env_value DB_DATABASE)"
        ;;
esac

rm -rf storage/app/public
tar -xzf "$MEDIA_FILE" -C storage/app
php artisan storage:link --no-interaction 2>/dev/null || true
php artisan optimize:clear
php artisan optimize
php artisan up

echo "Restauration terminée. Vérifiez le site et le back-office."
