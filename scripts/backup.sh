#!/usr/bin/env bash
# Sauvegarde du site UAZ : base de données + dossier des médias, avec rotation.
#
# Usage      : scripts/backup.sh [dossier-de-sauvegarde]        (défaut : ../backups)
# Planifier  : 30 2 * * * /var/www/uaz/scripts/backup.sh >> /var/log/uaz-backup.log 2>&1   (cron, tous les jours à 2 h 30)
# Variables  : KEEP_DAYS (défaut 14), REMOTE (ex. « user@hote:/sauvegardes/uaz » pour copier via rsync/ssh)
#
# La base est lue dans .env (DB_CONNECTION, DB_HOST, DB_DATABASE…) : MySQL/MariaDB via mysqldump, SQLite par copie.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${1:-$APP_DIR/../backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y-%m-%d_%H%M%S)"

env_value() { { grep -E "^$1=" "$APP_DIR/.env" || true; } | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

mkdir -p "$DEST"
umask 077   # sauvegardes lisibles par le seul propriétaire

DB_CONNECTION="$(env_value DB_CONNECTION)"

case "$DB_CONNECTION" in
    mysql|mariadb)
        DB_FILE="$DEST/db_$STAMP.sql.gz"
        # Mot de passe passé par variable d'environnement (invisible dans `ps`), jamais dans la ligne de commande.
        MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump \
            --host="$(env_value DB_HOST)" --port="$(env_value DB_PORT)" --user="$(env_value DB_USERNAME)" \
            --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 \
            "$(env_value DB_DATABASE)" | gzip -9 > "$DB_FILE"
        ;;
    sqlite)
        DB_PATH="$(env_value DB_DATABASE)"; DB_PATH="${DB_PATH:-$APP_DIR/database/database.sqlite}"
        DB_FILE="$DEST/db_$STAMP.sqlite.gz"
        gzip -9 -c "$DB_PATH" > "$DB_FILE"
        ;;
    *)
        echo "DB_CONNECTION « $DB_CONNECTION » non pris en charge" >&2; exit 1 ;;
esac

MEDIA_FILE="$DEST/media_$STAMP.tar.gz"
# tar renvoie 1 (« fichier modifié pendant la lecture ») si un rédacteur envoie une image au même moment : c'est un simple
# avertissement ; seules les erreurs réelles (code ≥ 2) interrompent la sauvegarde.
tar -czf "$MEDIA_FILE" -C "$APP_DIR/storage/app" public || [ $? -eq 1 ]

# Contrôle d'intégrité : les archives doivent être lisibles et non vides.
gzip -t "$DB_FILE" && gzip -t "$MEDIA_FILE"
[ -s "$DB_FILE" ] || { echo "Dump vide : $DB_FILE" >&2; exit 1; }

# Rotation : suppression des sauvegardes plus anciennes que KEEP_DAYS jours.
find "$DEST" -maxdepth 1 -type f \( -name 'db_*' -o -name 'media_*' \) -mtime +"$KEEP_DAYS" -delete

# Copie hors serveur (recommandée : une sauvegarde sur la même machine ne protège pas d'une panne disque).
if [ -n "${REMOTE:-}" ]; then
    rsync -a --partial "$DB_FILE" "$MEDIA_FILE" "$REMOTE/"
fi

echo "$(date '+%F %T') OK — $(basename "$DB_FILE") ($(du -h "$DB_FILE" | cut -f1)), $(basename "$MEDIA_FILE") ($(du -h "$MEDIA_FILE" | cut -f1))"
