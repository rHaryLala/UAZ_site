#!/usr/bin/env bash
# Mise en production de zurcher.edu.mg (site Laravel) sur le serveur PARTAGÉ (registrariat, Moodle, bibliothèque… tournent sur la même
# machine). À lancer en root, en trois temps :
#
#   sudo ADMIN_EMAIL=vous@exemple.mg bash provision-prod.sh prepare    # installe le site à côté de l'existant ; rien ne change pour les visiteurs
#   sudo bash provision-prod.sh switch                                  # zurcher.edu.mg passe sur le nouveau site (annulable)
#   sudo bash provision-prod.sh rollback                                # retour au site Next.js, en quelques secondes
#   sudo bash provision-prod.sh status
#
# Préparé à l'avance, par SSH, dans $STAGE (rien d'autre à envoyer) :
#   app/                  code + vendor/ + public/build + public/videos
#   storage-public.tar    storage/app/public (photos, documents et leurs conversions)
#   database.sqlite       base du poste de développement (copiée dans MariaDB, puis supprimée)
#
# Ce script ne crée aucun utilisateur système, ne touche ni à la configuration PHP commune ni aux autres sites Nginx, et n'écrase jamais
# une base déjà importée : « prepare » peut être relancé sans risque pour le contenu.
set -euo pipefail

PHASE="${1:-}"
DOMAIN="${DOMAIN:-zurcher.edu.mg}"
APP_DIR="${APP_DIR:-/var/www/uaz-laravel}"
STAGE="${STAGE:-/home/rabenamana/uaz-deploy}"
DB_NAME="${DB_NAME:-uaz_laravel}"
DB_USER="${DB_USER:-uaz_laravel}"
SECRETS_DIR="/root/.uaz-laravel"
SERVICE="uaz-laravel-queue"
AVAIL=/etc/nginx/sites-available
ENABLED=/etc/nginx/sites-enabled
NEW_CONF=uaz-laravel          # site Laravel (zurcher.edu.mg)
PREVIEW_CONF=uaz-laravel-preview   # même site sur 127.0.0.1:8088, pour le tester avant la bascule
OLD_CONF=uaz-site             # site Next.js actuel (conservé tel quel pour le retour arrière)
PREVIEW_PORT=8088

step() { printf '\n\033[1;34m→ %s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
die()  { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "À lancer en root (sudo)."
as_web()  { runuser -u www-data -- env HOME=/tmp "$@"; }   # www-data a /var/www pour répertoire personnel, non modifiable : HOME temporaire (PsySH, Composer…)
artisan() { (cd "$APP_DIR" && as_web php artisan "$@"); }
set_env() { local f="$APP_DIR/.env"; sed -i "/^$1=/d" "$f"; printf '%s=%s\n' "$1" "$2" >> "$f"; }
reload_nginx() { nginx -t && systemctl reload nginx; }

preview_check() { # preview_check CHEMIN CODE_ATTENDU
    local code; code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 -H "Host: $DOMAIN" "http://127.0.0.1:$PREVIEW_PORT$1" || true)"
    if [ "$code" = "$2" ]; then ok "$1 → $code"; else warn "$1 → $code (attendu $2)"; FAILED=1; fi
}

# ─────────────────────────────────────────────────────────────
write_nginx_confs() {
    local php_block='
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120s;
        internal;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }'

    local statics='
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }
    location /storage/ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }
    location ~* \.(?:ico|png|webp|avif|svg|woff2|mp4)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }'

    local ssl_main='
    listen [::]:443 ssl;
    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/zurcher.edu.mg/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/zurcher.edu.mg/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;'

    cat > "$AVAIL/$NEW_CONF" <<EOF
# Site UAZ (Laravel, PHP-FPM) servi sur zurcher.edu.mg. Remplace « $OLD_CONF » (Next.js), conservé dans sites-available pour le retour arrière.
# Généré par scripts/provision-prod.sh : ne pas modifier à la main sans reporter le changement dans ce script.

server {
    server_name zurcher.edu.mg;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 26M;
    server_tokens off;

    gzip on;
    gzip_comp_level 6;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml text/plain;
    gzip_min_length 1024;

    # Ancien site Next.js : le français était servi sous /fr ; ici il est à la racine
    location ~ ^/fr(/|\$) {
        rewrite ^/fr/?(.*)\$ /\$1 permanent;
    }

    # Vérification Google Search Console du domaine (fichier de l'ancien site)
    location = /googlefcb3f4498216fc94.html {
        root /var/www/wordpress;
    }

    # Fichiers et outils de l'ancien WordPress : redirigés vers l'ancien site conservé (ancien.zurcher.edu.mg)
    location ~ ^/(wp-content|wp-includes|wp-admin|wp-json|wp-login\.php|xmlrpc\.php|wp-cron\.php|inventory|nouveau|public_html|rhm|student_association|test|views|webradio|default\.php|llms\.txt)(/|\$) {
        return 301 https://ancien.zurcher.edu.mg\$request_uri;
    }
$statics
$php_block
$ssl_main
}

# www et l'ancienne adresse de test : redirection permanente vers zurcher.edu.mg
server {
    server_name www.zurcher.edu.mg;
$ssl_main
    return 301 https://zurcher.edu.mg\$request_uri;
}

server {
    server_name new.zurcher.edu.mg;
    listen [::]:443 ssl;
    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/new.zurcher.edu.mg/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/new.zurcher.edu.mg/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    return 301 https://zurcher.edu.mg\$request_uri;
}

server {
    listen 80;
    listen [::]:80;
    server_name zurcher.edu.mg www.zurcher.edu.mg new.zurcher.edu.mg;
    return 301 https://zurcher.edu.mg\$request_uri;
}
EOF

    # Aperçu : le même site, uniquement joignable depuis le serveur lui-même (127.0.0.1), pour le tester avant la bascule.
    cat > "$AVAIL/$PREVIEW_CONF" <<EOF
# Aperçu local du site Laravel (127.0.0.1 uniquement) : tester avec  curl -H 'Host: zurcher.edu.mg' http://127.0.0.1:$PREVIEW_PORT/
server {
    listen 127.0.0.1:$PREVIEW_PORT;
    server_name _;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 26M;
$statics
$php_block
}
EOF
}

# ─────────────────────────────────────────────────────────────
phase_prepare() {
    step "0. Vérifications préalables"
    [ -f "$STAGE/app/artisan" ] || die "Code introuvable : $STAGE/app/artisan"
    [ -f "$STAGE/app/vendor/autoload.php" ] || die "vendor/ manquant dans $STAGE/app (composer install --no-dev)"
    [ -f "$STAGE/app/public/build/manifest.json" ] || die "public/build manquant dans $STAGE/app"
    [ -f "$STAGE/storage-public.tar" ] || die "Médias introuvables : $STAGE/storage-public.tar"
    [ -f "$STAGE/database.sqlite" ] || [ -f "$APP_DIR/storage/.data-imported" ] || die "Base introuvable : $STAGE/database.sqlite"
    nginx -t 2>/dev/null || die "La configuration Nginx actuelle est déjà en erreur : rien n'est modifié (nginx -t)"
    ok "fichiers préparés présents, Nginx sain"
    mkdir -p "$SECRETS_DIR" && chmod 700 "$SECRETS_DIR"

    step "1. Base de données ($DB_NAME)"
    local pass_file="$SECRETS_DIR/db-pass" db_pass
    [ -f "$pass_file" ] || { openssl rand -base64 24 | tr -d '/+=\n' > "$pass_file"; chmod 600 "$pass_file"; }
    db_pass="$(cat "$pass_file")"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$db_pass';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$db_pass';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "base et utilisateur prêts (mot de passe dans $pass_file)"

    step "2. Code dans $APP_DIR"
    mkdir -p "$APP_DIR"
    rsync -a --delete --exclude=/.env --exclude=/storage/ --exclude=/public/storage "$STAGE/app/" "$APP_DIR/"
    mkdir -p "$APP_DIR"/storage/{app/public,framework/{cache,sessions,views},logs} "$APP_DIR/bootstrap/cache"
    chown -R root:www-data "$APP_DIR"
    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    find "$APP_DIR" -type d -not -path "$APP_DIR/storage/*" -not -path "$APP_DIR/bootstrap/cache/*" -exec chmod 755 {} +
    find "$APP_DIR" -type f -not -path "$APP_DIR/storage/*" -not -path "$APP_DIR/bootstrap/cache/*" -exec chmod 644 {} +
    chmod -R ug+rwX,o-rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod +x "$APP_DIR/artisan"
    ln -sfn ../storage/app/public "$APP_DIR/public/storage"
    ok "code en place"

    step "3. Configuration (.env)"
    if [ ! -f "$APP_DIR/.env" ]; then
        cp "$APP_DIR/.env.production.example" "$APP_DIR/.env"
        warn ".env créé depuis .env.production.example"
    else
        ok ".env existant conservé"
    fi
    chown www-data:www-data "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "https://$DOMAIN"
    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "\"$db_pass\""
    set_env SESSION_SECURE_COOKIE true
    set_env QUEUE_CONNECTION database
    set_env SEED_ADMIN_EMAIL ""
    set_env SEED_ADMIN_PASSWORD ""
    set_env MEDIA_AVIF false   # PHP du serveur : WebP oui, AVIF non (les versions AVIF déjà créées sont conservées)
    # Courrier : tant qu'aucun SMTP n'est renseigné, rien ne part ; les messages du formulaire de contact restent enregistrés dans le back-office.
    if ! grep -q '^MAIL_HOST=.' "$APP_DIR/.env"; then
        set_env MAIL_MAILER log
        set_env MAIL_FROM_ADDRESS "no-reply@zurcher.edu.mg"
        set_env CONTACT_RECIPIENT "office@zurcher.edu.mg"
        warn "courrier : MAIL_MAILER=log (aucun SMTP configuré) — à renseigner dans $APP_DIR/.env"
    fi
    if ! grep -q '^APP_KEY=base64:' "$APP_DIR/.env"; then
        artisan key:generate --force
        cp "$APP_DIR/.env" "$SECRETS_DIR/env.backup" && chmod 600 "$SECRETS_DIR/env.backup"
        warn "APP_KEY générée ; copie du .env dans $SECRETS_DIR/env.backup (à conserver)"
    fi

    step "4. Base : structure puis contenu"
    artisan migrate --force --no-interaction
    if [ -f "$APP_DIR/storage/.data-imported" ]; then
        ok "contenu déjà importé (ignoré : la base de production n'est jamais écrasée)"
    else
        # Le dossier de préparation est dans le répertoire personnel (illisible par www-data) : copie temporaire dans le site.
        cp "$STAGE/database.sqlite" "$APP_DIR/storage/import.sqlite" && chown www-data:www-data "$APP_DIR/storage/import.sqlite"
        artisan db:import-sqlite "$APP_DIR/storage/import.sqlite" --no-interaction || { rm -f "$APP_DIR/storage/import.sqlite"; die "import de la base interrompu (voir ci-dessus) ; « prepare » peut être relancé"; }
        rm -f "$APP_DIR/storage/import.sqlite"
        tar -xf "$STAGE/storage-public.tar" -C "$APP_DIR/storage/app"
        chown -R www-data:www-data "$APP_DIR/storage/app/public"
        as_web touch "$APP_DIR/storage/.data-imported"
        rm -f "$STAGE/database.sqlite"   # la base de développement (comptes, messages d'essai) ne reste pas sur le serveur
        ok "contenu et médias importés ; fichier SQLite supprimé"
    fi

    # Compte administrateur : étape à part (relançable seule). Le premier compte super-admin reprend l'adresse donnée.
    if [ -f "$SECRETS_DIR/admin-login" ]; then
        ok "compte administrateur déjà créé (identifiants dans $SECRETS_DIR/admin-login)"
    else
        [ -n "${ADMIN_EMAIL:-}" ] || die "ADMIN_EMAIL est requis pour créer le compte administrateur : sudo ADMIN_EMAIL=vous@exemple.mg bash $(basename "$0") prepare"
        ADMIN_PASS="$(openssl rand -base64 18 | tr -d '/+=\n')"
        (cd "$APP_DIR" && as_web env ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASS="$ADMIN_PASS" php -r '
            require "vendor/autoload.php";
            $app = require "bootstrap/app.php";
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $u = App\Models\User::role("super-admin")->orderBy("id")->first() ?? App\Models\User::orderBy("id")->first();
            if (! $u) { fwrite(STDERR, "aucun compte à promouvoir\n"); exit(1); }
            $u->forceFill(["email" => getenv("ADMIN_EMAIL"), "name" => "Administrateur UAZ", "password" => Illuminate\Support\Facades\Hash::make(getenv("ADMIN_PASS"))])->save();
            echo "  compte administrateur : ".$u->email." (rôles : ".$u->getRoleNames()->implode(", ").")".PHP_EOL;') || die "création du compte administrateur impossible"
        printf '%s / %s\n' "$ADMIN_EMAIL" "$ADMIN_PASS" > "$SECRETS_DIR/admin-login"; chmod 600 "$SECRETS_DIR/admin-login"
        ok "identifiants écrits dans $SECRETS_DIR/admin-login"
    fi
    artisan optimize:clear
    artisan optimize
    artisan filament:optimize 2>/dev/null || true

    step "5. File d'attente et planificateur"
    cat > "/etc/systemd/system/$SERVICE.service" <<EOF
[Unit]
Description=File d'attente UAZ (Laravel)
After=network.target mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/$SERVICE.log
StandardError=append:/var/log/$SERVICE.log

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable --now "$SERVICE" >/dev/null 2>&1
    systemctl restart "$SERVICE"
    echo "* * * * * www-data cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/uaz-laravel
    chmod 644 /etc/cron.d/uaz-laravel
    ok "$SERVICE actif ; planificateur installé"

    step "6. Nginx : aperçu local (zurcher.edu.mg reste sur l'ancien site)"
    write_nginx_confs
    ln -sfn "../sites-available/$PREVIEW_CONF" "$ENABLED/$PREVIEW_CONF"
    reload_nginx
    ok "aperçu sur http://127.0.0.1:$PREVIEW_PORT (visible seulement depuis le serveur)"

    step "7. Tests sur l'aperçu"
    FAILED=0
    preview_check /up 200
    preview_check / 200
    preview_check /formations 200
    preview_check /formations/licence-en-communication 200
    preview_check /actualites 200
    preview_check /contact 200
    preview_check /admin/login 200
    preview_check /sitemap.xml 200
    preview_check /robots.txt 200
    preview_check /en 200
    preview_check /mg 200
    [ "$FAILED" -eq 0 ] && ok "tous les contrôles passent" || warn "des contrôles échouent : ne basculez pas avant de les avoir examinés ($APP_DIR/storage/logs, /var/log/nginx/error.log)"

    cat <<EOF

════════════════════════════════════════════════════════════
 Site Laravel prêt, NON encore en ligne sur $DOMAIN.
 Dossier : $APP_DIR      Base : $DB_NAME
 Compte administrateur : voir $SECRETS_DIR/admin-login (lisible en root seulement)
 Étape suivante : sudo bash $(basename "$0") switch
════════════════════════════════════════════════════════════
EOF
}

# ─────────────────────────────────────────────────────────────
phase_switch() {
    step "Bascule de $DOMAIN vers le site Laravel"
    [ -f "$AVAIL/$NEW_CONF" ] || die "Lancez d'abord « prepare »."
    [ -f "$APP_DIR/storage/.data-imported" ] || die "Le contenu n'est pas importé : relancez « prepare »."
    FAILED=0; preview_check /up 200; preview_check / 200
    [ "$FAILED" -eq 0 ] || die "L'aperçu ne répond pas correctement : bascule annulée."

    # Une seule déclaration par nom de domaine : on retire l'ancien lien (le fichier reste dans sites-available).
    local was_old=0
    [ -L "$ENABLED/$OLD_CONF" ] && was_old=1
    rm -f "$ENABLED/$OLD_CONF"
    ln -sfn "../sites-available/$NEW_CONF" "$ENABLED/$NEW_CONF"
    if ! nginx -t; then
        warn "configuration invalide : retour à l'ancien site"
        rm -f "$ENABLED/$NEW_CONF"; [ "$was_old" = 1 ] && ln -sfn "../sites-available/$OLD_CONF" "$ENABLED/$OLD_CONF"
        die "bascule annulée, rien n'a changé pour les visiteurs"
    fi
    systemctl reload nginx

    local code; code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/up" || true)"
    if [ "$code" != "200" ]; then
        warn "https://$DOMAIN/up → $code : retour automatique à l'ancien site"
        phase_rollback; die "bascule annulée"
    fi
    ok "https://$DOMAIN est servi par le site Laravel"

    # Conversions JPEG des photos (lettres d'information) : en arrière-plan, à faible priorité, sans gêner les autres sites du serveur.
    (cd "$APP_DIR" && nohup nice -n 19 runuser -u www-data -- php artisan media-library:regenerate --only-missing --force >> "$APP_DIR/storage/logs/regenerate.log" 2>&1 &) || true
    ok "conversions d'images manquantes lancées en arrière-plan (storage/logs/regenerate.log)"
    cat <<EOF

 Vérifiez https://$DOMAIN puis https://$DOMAIN/admin.
 Retour arrière immédiat :  sudo bash $(basename "$0") rollback
EOF
}

phase_rollback() {
    step "Retour au site Next.js"
    [ -f "$AVAIL/$OLD_CONF" ] || die "$AVAIL/$OLD_CONF introuvable : impossible de revenir en arrière automatiquement"
    rm -f "$ENABLED/$NEW_CONF"
    ln -sfn "../sites-available/$OLD_CONF" "$ENABLED/$OLD_CONF"
    reload_nginx
    ok "$DOMAIN est de nouveau servi par le site Next.js (processus sur le port 3100, jamais arrêté)"
}

phase_status() {
    echo "Site actif sur $DOMAIN :"
    if [ -L "$ENABLED/$NEW_CONF" ]; then echo "  Laravel ($NEW_CONF)"; elif [ -L "$ENABLED/$OLD_CONF" ]; then echo "  Next.js ($OLD_CONF)"; else echo "  ?"; fi
    systemctl is-active "$SERVICE" 2>/dev/null | sed 's/^/  file d attente : /' || true
    [ -f "$APP_DIR/storage/.data-imported" ] && echo "  contenu importé : oui" || echo "  contenu importé : non"
}

case "$PHASE" in
    prepare)  phase_prepare ;;
    switch)   phase_switch ;;
    rollback) phase_rollback ;;
    status)   phase_status ;;
    *) die "Usage : provision-prod.sh prepare | switch | rollback | status" ;;
esac
