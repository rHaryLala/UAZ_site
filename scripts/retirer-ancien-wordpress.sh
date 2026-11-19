#!/usr/bin/env bash
# Retrait de l'ancien site WordPress du serveur (décision D-088). À lancer sur le serveur, en root, dans cet ordre :
#
#   sudo bash retirer-ancien-wordpress.sh copier      1. copie les 4 fichiers que le nouveau site utilise encore dans son dossier
#   sudo bash provision-prod.sh update                2. déploie le code qui pointe vers ces fichiers (liens des pages, Yearbook, fichier Google)
#   sudo bash retirer-ancien-wordpress.sh nginx       3. retire « ancien.zurcher.edu.mg » et les redirections vers l'ancien site
#   sudo bash retirer-ancien-wordpress.sh supprimer   4. SUPPRIME /var/www/wordpress, les bases « wordpress* » et le certificat (irréversible)
#   sudo bash retirer-ancien-wordpress.sh status      état des étapes
#
# Chaque étape peut être relancée sans risque. L'étape 4 refuse de s'exécuter si les précédentes ne sont pas faites.

set -Eeuo pipefail

OLD_DIR=/var/www/wordpress
APP_DIR=/var/www/uaz-laravel
DOCS="$APP_DIR/storage/app/public/documents"
NGINX_SITE=/etc/nginx/sites-available/uaz-laravel
OLD_CONF=/etc/nginx/conf.d/wordpress.conf
BACKUPS=/root/.uaz-laravel/backups
DOMAIN=zurcher.edu.mg
OLD_HOST=ancien.zurcher.edu.mg

# ancien chemin (sous uploads/) -> nom dans le nouveau site
FICHIERS=(
    "2025/11/Formulaire-de-demande-dadmission-new.docx|Formulaire-de-demande-dadmission-new.docx"
    "2025/11/Formulaire-de-recommandation-new-1.doc|Formulaire-de-recommandation-new-1.doc"
    "2025/11/Formulaire-de-demande-dadmission-Master-2025.docx|Formulaire-de-demande-dadmission-Master-2025.docx"
    "2025/12/Yearbook-2025-.pdf|Yearbook-2025.pdf"
)

ok()   { printf '  \033[32m✔\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
step() { printf '\n\033[1m%s\033[0m\n' "$*"; }
die()  { printf '  \033[31m✘ %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" = 0 ] || die "À lancer avec sudo."

fichiers_copies() {
    local ligne dest
    for ligne in "${FICHIERS[@]}"; do
        dest="${ligne#*|}"
        [ -s "$DOCS/$dest" ] || return 1
    done
}

phase_copier() {
    step "1. Copie des fichiers utilisés par le nouveau site"
    [ -d "$APP_DIR" ] || die "$APP_DIR introuvable."
    mkdir -p "$DOCS"
    local ligne src dest
    for ligne in "${FICHIERS[@]}"; do
        src="$OLD_DIR/wp-content/uploads/${ligne%%|*}"
        dest="${ligne#*|}"
        if [ -s "$DOCS/$dest" ]; then ok "$dest déjà présent"; continue; fi
        [ -s "$src" ] || die "Fichier source introuvable : $src (l'ancien dossier a-t-il déjà été supprimé ?)"
        cp -f "$src" "$DOCS/$dest"
        ok "$dest copié ($(du -h "$DOCS/$dest" | cut -f1))"
    done
    chown -R www-data:www-data "$DOCS"
    chmod 755 "$DOCS"
    chmod 644 "$DOCS"/*
    echo
    echo "  Étape suivante : sudo bash provision-prod.sh update"
}

phase_nginx() {
    step "3. Nginx : fin de l'ancien site"
    fichiers_copies || die "Les fichiers ne sont pas copiés : lancez d'abord « copier »."
    [ -f "$APP_DIR/public/googlefcb3f4498216fc94.html" ] || die "Le code à jour n'est pas déployé (fichier Google absent) : lancez « provision-prod.sh update » d'abord."
    [ -f "$NGINX_SITE" ] || die "$NGINX_SITE introuvable."

    local ts; ts="$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUPS"
    cp -a "$NGINX_SITE" "$BACKUPS/nginx-uaz-laravel-$ts"
    [ -f "$OLD_CONF" ] && cp -a "$OLD_CONF" "$BACKUPS/nginx-wordpress.conf-$ts"

    NGINX_SITE="$NGINX_SITE" python3 - <<'PY'
import os, re, sys

chemin = os.environ["NGINX_SITE"]
texte = open(chemin, encoding="utf-8").read()

neuf = """    # Anciens liens WordPress (retrait de l'ancien site, D-088) : les quatre téléchargements encore cités par des sites externes ont été
    # déplacés dans /storage/documents/ ; tout le reste de l'ancien WordPress n'existe plus (410 Gone).
    location = /wp-content/uploads/2025/12/Yearbook-2025-.pdf {
        return 301 /storage/documents/Yearbook-2025.pdf;
    }
    location ~ ^/wp-content/uploads/[0-9][0-9][0-9][0-9]/[0-9][0-9]/(Formulaire-de-demande-dadmission-new\\.docx|Formulaire-de-recommandation-new-1\\.doc|Formulaire-de-demande-dadmission-Master-2025\\.docx)$ {
        return 301 /storage/documents/$1;
    }
    location ~ ^/(wp-content|wp-includes|wp-admin|wp-json|wp-login\\.php|xmlrpc\\.php|wp-cron\\.php|default\\.php|llms\\.txt)(/|$) {
        return 410;
    }
"""

modele = re.compile(r"    # Vérification Google Search Console.*?return 301 https://ancien\.zurcher\.edu\.mg\$request_uri;\n    \}\n", re.S)
if "Anciens liens WordPress (retrait" in texte:
    print("déjà à jour")
    sys.exit(0)
resultat, n = modele.subn(lambda _: neuf, texte, count=1)
if n != 1:
    print("bloc de l'ancien site introuvable dans " + chemin, file=sys.stderr)
    sys.exit(2)
open(chemin, "w", encoding="utf-8").write(resultat)
print("modifié")
PY

    if [ -f "$OLD_CONF" ]; then
        rm -f "$OLD_CONF"
        ok "$OLD_CONF retiré (copie dans $BACKUPS)"
    fi

    if ! nginx -t 2>/tmp/nginx-test.log; then
        cat /tmp/nginx-test.log
        cp -a "$BACKUPS/nginx-uaz-laravel-$ts" "$NGINX_SITE"
        [ -f "$BACKUPS/nginx-wordpress.conf-$ts" ] && cp -a "$BACKUPS/nginx-wordpress.conf-$ts" "$OLD_CONF"
        nginx -t && true
        die "Configuration invalide : l'ancienne configuration est remise en place, rien n'a changé."
    fi
    systemctl reload nginx
    ok "nginx rechargé"

    step "Vérifications"
    local code
    check() { # check URL ATTENDU
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN$1" || true)"
        if [ "$code" = "$2" ]; then ok "$1 → $code"; else warn "$1 → $code (attendu : $2)"; fi
    }
    check /googlefcb3f4498216fc94.html 200
    check /storage/documents/Yearbook-2025.pdf 200
    check /storage/documents/Formulaire-de-demande-dadmission-new.docx 200
    check /wp-content/uploads/2025/12/Yearbook-2025-.pdf 301
    check /wp-admin/ 410
    check / 200
    echo
    echo "  Étape suivante (irréversible) : sudo bash $(basename "$0") supprimer"
}

phase_supprimer() {
    step "4. Suppression de l'ancien WordPress (IRRÉVERSIBLE)"
    fichiers_copies || die "Les fichiers utilisés ne sont pas copiés dans $DOCS : lancez « copier »."
    [ ! -f "$OLD_CONF" ] || die "$OLD_CONF existe encore : lancez « nginx » d'abord."
    grep -q "Anciens liens WordPress (retrait" "$NGINX_SITE" || die "nginx n'est pas encore à jour : lancez « nginx » d'abord."
    [ -f "$APP_DIR/public/googlefcb3f4498216fc94.html" ] || die "Le code à jour n'est pas déployé : lancez « provision-prod.sh update »."

    echo "  Seront supprimés :"
    echo "    - le dossier $OLD_DIR ($(du -sh "$OLD_DIR" 2>/dev/null | cut -f1))"
    echo "    - les bases MariaDB « wordpress » et « wordpress_sauvegarde »"
    echo "    - le certificat de $OLD_HOST"
    echo "  Les 4 fichiers utilisés sont déjà dans $DOCS."
    printf '  Tapez OUI en majuscules pour continuer : '
    local reponse; read -r reponse
    [ "$reponse" = "OUI" ] || die "Annulé, rien n'a été supprimé."

    rm -rf "$OLD_DIR"
    ok "$OLD_DIR supprimé"

    mysql -e "DROP DATABASE IF EXISTS wordpress; DROP DATABASE IF EXISTS wordpress_sauvegarde;" && ok "bases wordpress et wordpress_sauvegarde supprimées" || warn "suppression des bases impossible : à faire à la main (mysql -e 'DROP DATABASE wordpress;')"

    if certbot certificates 2>/dev/null | grep -q "Certificate Name: $OLD_HOST"; then
        certbot delete --cert-name "$OLD_HOST" --non-interactive && ok "certificat de $OLD_HOST supprimé" || warn "certificat non supprimé : certbot delete --cert-name $OLD_HOST"
    else
        ok "pas de certificat pour $OLD_HOST"
    fi

    systemctl reload nginx
    df -h / | tail -1 | awk '{print "  Disque : " $3 " utilisés sur " $2 " (" $5 ")"}'
    echo
    echo "  Reste à faire chez Hostinger (DNS) : supprimer l'enregistrement « $OLD_HOST » devenu inutile."
}

phase_status() {
    step "État du retrait de l'ancien WordPress"
    if fichiers_copies; then ok "1. fichiers copiés dans $DOCS"; else warn "1. fichiers non copiés"; fi
    if [ -f "$APP_DIR/public/googlefcb3f4498216fc94.html" ]; then ok "2. code à jour déployé"; else warn "2. code à jour non déployé (provision-prod.sh update)"; fi
    if grep -q "Anciens liens WordPress (retrait" "$NGINX_SITE" 2>/dev/null && [ ! -f "$OLD_CONF" ]; then ok "3. nginx à jour"; else warn "3. nginx pas encore à jour"; fi
    if [ -d "$OLD_DIR" ]; then warn "4. $OLD_DIR présent ($(du -sh "$OLD_DIR" 2>/dev/null | cut -f1))"; else ok "4. ancien WordPress supprimé"; fi
}

case "${1:-}" in
    copier) phase_copier ;;
    nginx) phase_nginx ;;
    supprimer) phase_supprimer ;;
    status) phase_status ;;
    *) die "Usage : retirer-ancien-wordpress.sh copier | nginx | supprimer | status" ;;
esac
