#!/usr/bin/env bash
# Installe (ou met à jour) le site de TEST sur le serveur, en une seule exécution. À lancer en root, sur le serveur.
#
#   Poste de dev :  git archive --format=tar.gz -o uaz.tar.gz HEAD
#                   tar -czf uaz-assets.tar.gz public/build public/videos
#                   scp uaz.tar.gz uaz-assets.tar.gz scripts/provision-test.sh root@SERVEUR:/tmp/
#   Serveur :       EMAIL=vous@exemple.mg bash /tmp/provision-test.sh
#
# Variables facultatives : DOMAIN, APP_DIR, ARCHIVE, ASSETS, EMAIL (Let's Encrypt), EXPECTED_IP, BASIC_USER.
# Relançable sans risque : .env, base, médias et mot de passe Nginx existants sont conservés.
set -euo pipefail

DOMAIN="${DOMAIN:-test.zurcher.edu.mg}"
APP_DIR="${APP_DIR:-/var/www/uaz-test}"
ARCHIVE="${ARCHIVE:-/tmp/uaz.tar.gz}"
ASSETS="${ASSETS:-/tmp/uaz-assets.tar.gz}"
EMAIL="${EMAIL:-}"
EXPECTED_IP="${EXPECTED_IP:-191.215.37.182}"
BASIC_USER="${BASIC_USER:-testeur}"
BASIC_AUTH="${BASIC_AUTH:-0}"   # 1 = protéger tout le site par mot de passe Nginx (sinon public, mais noindex)
DB_NAME="${DB_NAME:-uaz_test}"
DB_USER="${DB_USER:-uaz_test}"
SECRETS_DIR="/root/.uaz-test"
SERVICE="uaz-test-queue"

step() { printf '\n\033[1;34m→ %s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
die()  { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

as_deploy() { runuser -u deploy -- env HOME=/home/deploy "$@"; }
as_web()    { runuser -u www-data -- "$@"; }
artisan()   { (cd "$APP_DIR" && as_web php artisan "$@"); }

set_env() { # set_env CLE VALEUR : remplace la ligne (ou l'ajoute)
    local file="$APP_DIR/.env"
    sed -i "/^$1=/d" "$file"
    printf '%s=%s\n' "$1" "$2" >> "$file"
}

# ─────────────────────────────────────────────────────────────
step "0. Vérifications préalables"
[ "$(id -u)" -eq 0 ] || die "À lancer en root."
. /etc/os-release
if [ "${ID:-}" = "ubuntu" ] && [ "${VERSION_ID:-}" = "24.04" ]; then ok "Ubuntu 24.04"; else warn "OS : ${PRETTY_NAME:-inconnu} (testé sur Ubuntu 24.04)"; fi
[ -f "$ARCHIVE" ] || die "Archive du code introuvable : $ARCHIVE"
[ -f "$ASSETS" ] || die "Archive des assets introuvable : $ASSETS (public/build + public/videos)"
ok "archives présentes"
mkdir -p "$SECRETS_DIR" && chmod 700 "$SECRETS_DIR"

# ─────────────────────────────────────────────────────────────
step "1. Vérification (et complément) de l'étape 2 : paquets, PHP, utilisateur, pare-feu"
PACKAGES=(nginx mariadb-server git unzip curl ufw fail2ban certbot python3-certbot-nginx apache2-utils
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip
    php8.3-gd php8.3-intl php8.3-bcmath)
MISSING=()
for p in "${PACKAGES[@]}"; do dpkg -s "$p" >/dev/null 2>&1 || MISSING+=("$p"); done
if [ ${#MISSING[@]} -gt 0 ]; then
    warn "paquets manquants : ${MISSING[*]}"
    apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y "${MISSING[@]}"
fi
ok "tous les paquets sont installés"

if ! command -v composer >/dev/null; then
    warn "Composer absent : installation"
    curl -fsS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
ok "composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')"

php -v | head -1 | grep -q 'PHP 8\.3' || warn "PHP CLI n'est pas en 8.3 : $(php -v | head -1)"
PHP_MODULES="$(php -m)"   # dans une variable : « php -m | grep -q » échoue (SIGPIPE) sous pipefail dès que grep s'arrête à la première ligne trouvée
for ext in pdo_mysql mbstring xml curl zip gd intl bcmath fileinfo; do
    grep -qix "$ext" <<<"$PHP_MODULES" || die "Extension PHP manquante : $ext"
done
ok "extensions PHP requises présentes"
php -r 'echo "  WebP : ", function_exists("imagewebp") ? "oui" : "NON", " / AVIF : ", function_exists("imageavif") ? "oui" : "non (WebP seul)", PHP_EOL;'

INI=/etc/php/8.3/fpm/conf.d/99-uaz.ini
if [ ! -f "$INI" ]; then
    cat > "$INI" <<'EOF'
expose_php = Off
memory_limit = 256M
upload_max_filesize = 12M
post_max_size = 16M
date.timezone = Indian/Antananarivo
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax
EOF
    warn "$INI créé (validate_timestamps=1 pour le test)"
fi
systemctl enable --now php8.3-fpm mariadb nginx >/dev/null 2>&1
systemctl reload php8.3-fpm
ok "php8.3-fpm, mariadb, nginx actifs"

id deploy >/dev/null 2>&1 || { adduser --disabled-password --gecos "" deploy; warn "utilisateur deploy créé"; }
id -nG deploy | tr ' ' '\n' | grep -qx www-data || usermod -aG www-data deploy
ok "utilisateur deploy (groupe www-data)"

if ufw status | grep -q "Status: active"; then
    ufw status | grep -q "Nginx Full" || { ufw allow 'Nginx Full' >/dev/null; warn "règle Nginx Full ajoutée"; }
    ok "pare-feu actif"
else
    warn "pare-feu inactif : ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable (non fait ici pour ne pas couper votre SSH)"
fi

# ─────────────────────────────────────────────────────────────
step "2. Base de données ($DB_NAME)"
DB_PASS_FILE="$SECRETS_DIR/db-pass"
[ -f "$DB_PASS_FILE" ] || { openssl rand -base64 24 | tr -d '/+=\n' > "$DB_PASS_FILE"; chmod 600 "$DB_PASS_FILE"; }
DB_PASS="$(cat "$DB_PASS_FILE")"
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "base et utilisateur prêts (mot de passe dans $DB_PASS_FILE)"

# ─────────────────────────────────────────────────────────────
step "3. Code et assets dans $APP_DIR"
mkdir -p "$APP_DIR"
tar -xzf "$ARCHIVE" -C "$APP_DIR"
rm -rf "$APP_DIR/public/build"
tar -xzf "$ASSETS" -C "$APP_DIR"
[ -f "$APP_DIR/artisan" ] || die "artisan introuvable après extraction : archive invalide ?"
[ -f "$APP_DIR/public/build/manifest.json" ] || die "public/build/manifest.json manquant : lancez « npm run build » avant l'archive."
ok "code et build extraits ($(ls "$APP_DIR/public/videos/"*.mp4 2>/dev/null | wc -l) vidéos)"

mkdir -p "$APP_DIR"/storage/{app/public,framework/{cache,sessions,views},logs} "$APP_DIR/bootstrap/cache"
chown -R deploy:www-data "$APP_DIR"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR" -type d -not -path "$APP_DIR/storage/*" -not -path "$APP_DIR/bootstrap/cache/*" -exec chmod 755 {} +
find "$APP_DIR" -type f -not -path "$APP_DIR/storage/*" -not -path "$APP_DIR/bootstrap/cache/*" -exec chmod 644 {} +
chmod -R ug+rwX,o-rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod g+s {} +
chmod +x "$APP_DIR/artisan" "$APP_DIR"/scripts/*.sh 2>/dev/null || true

# ─────────────────────────────────────────────────────────────
step "4. Configuration (.env)"
FIRST_INSTALL=0
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.production.example" "$APP_DIR/.env"
    FIRST_INSTALL=1
    warn ".env créé depuis .env.production.example"
else
    ok ".env existant conservé (seules les valeurs propres au test sont réécrites)"
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
set_env DB_PASSWORD "\"$DB_PASS\""
set_env SESSION_SECURE_COOKIE true
set_env QUEUE_CONNECTION database
set_env MAIL_MAILER log
set_env MAIL_FROM_ADDRESS "no-reply@zurcher.edu.mg"
set_env CONTACT_RECIPIENT "no-reply@zurcher.edu.mg"
set_env SEED_ADMIN_EMAIL ""
set_env SEED_ADMIN_PASSWORD ""
ok "mails redirigés vers les journaux (MAIL_MAILER=log) : rien n'est envoyé à de vraies adresses"

# ─────────────────────────────────────────────────────────────
step "5. Application"
as_deploy bash -c "umask 002 && cd '$APP_DIR' && composer install --no-dev --optimize-autoloader --no-interaction"
if ! grep -q '^APP_KEY=base64:' "$APP_DIR/.env"; then
    artisan key:generate --force
    cp "$APP_DIR/.env" "$SECRETS_DIR/env.backup" && chmod 600 "$SECRETS_DIR/env.backup"
    warn "APP_KEY générée ; copie du .env dans $SECRETS_DIR/env.backup (à conserver)"
fi
artisan migrate --force --no-interaction
if [ ! -f "$APP_DIR/storage/.provisioned" ]; then
    artisan db:seed --class=RolesAndPermissionsSeeder --force
    artisan db:seed --class=StructureSeeder --force
    as_web touch "$APP_DIR/storage/.provisioned"
    ok "données de base semées"
else
    ok "données de base déjà semées (ignoré)"
fi
# Lien créé en root : www-data n'a pas le droit d'écrire dans public/.
ln -sfn ../storage/app/public "$APP_DIR/public/storage"
chown -h deploy:www-data "$APP_DIR/public/storage"
[ -e "$APP_DIR/public/storage" ] || die "public/storage ne pointe sur rien"
artisan optimize:clear
artisan optimize
artisan filament:optimize 2>/dev/null || true
ok "application prête"

# ─────────────────────────────────────────────────────────────
step "6. Nginx (public, non indexé ; mot de passe si BASIC_AUTH=1)"
HTPASSWD=/etc/nginx/.htpasswd-uaz-test
BASIC_PASS_SHOWN=""
AUTH_LINES=""
if [ "$BASIC_AUTH" = 1 ]; then
    AUTH_LINES="auth_basic \"Test UAZ\";
    auth_basic_user_file $HTPASSWD;"
fi
if [ "$BASIC_AUTH" = 1 ] && [ ! -f "$HTPASSWD" ]; then
    BASIC_PASS_SHOWN="$(openssl rand -base64 12 | tr -d '/+=\n')"
    htpasswd -bc "$HTPASSWD" "$BASIC_USER" "$BASIC_PASS_SHOWN" >/dev/null
    chmod 640 "$HTPASSWD"; chown root:www-data "$HTPASSWD"
fi
mkdir -p /var/www/letsencrypt

# Ne pas écraser un bloc déjà passé par Certbot (il contient les lignes ssl_*).
CONF=/etc/nginx/sites-available/uaz-test
if [ -f "$CONF" ] && grep -q 'ssl_certificate' "$CONF"; then
    ok "configuration HTTPS existante conservée ($CONF)"
else
cat > "$CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 16m;
    server_tokens off;

    $AUTH_LINES

    # Site de test : jamais indexé
    add_header X-Robots-Tag "noindex, nofollow" always;
    location = /robots.txt {
        default_type text/plain;
        return 200 "User-agent: *\nDisallow: /\n";
    }

    gzip on;
    gzip_comp_level 6;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml text/plain;
    gzip_min_length 1024;

    # Validation Let's Encrypt (sans mot de passe)
    location ^~ /.well-known/acme-challenge/ {
        auth_basic off;
        root /var/www/letsencrypt;
    }

    # Contrôle de santé sans mot de passe
    location = /up {
        auth_basic off;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }

    location /storage/ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files \$uri =404;
    }

    location ~* \.(?:ico|png|webp|svg|woff2)\$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF
fi
ln -sf "$CONF" /etc/nginx/sites-enabled/uaz-test
nginx -t
systemctl reload nginx
ok "site Nginx actif sur $DOMAIN"

# ─────────────────────────────────────────────────────────────
step "7. HTTPS (Let's Encrypt)"
PUBLIC_IP="$(curl -4 -fsS --max-time 8 https://api.ipify.org 2>/dev/null || echo "$EXPECTED_IP")"
DNS_IP="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}')"
if grep -q 'ssl_certificate' "$CONF"; then
    ok "certificat déjà installé"
elif [ -z "$EMAIL" ]; then
    warn "EMAIL non fourni : HTTPS ignoré. Relancez avec EMAIL=vous@exemple.mg"
elif [ "$DNS_IP" != "$PUBLIC_IP" ]; then
    warn "le DNS de $DOMAIN pointe vers « ${DNS_IP:-rien} » et non vers $PUBLIC_IP : HTTPS ignoré. Créez l'enregistrement A, attendez la propagation, relancez."
else
    certbot certonly --webroot -w /var/www/letsencrypt -d "$DOMAIN" --agree-tos -m "$EMAIL" --non-interactive --keep-until-expiring
    certbot install --nginx --cert-name "$DOMAIN" --redirect --non-interactive
    nginx -t && systemctl reload nginx
    ok "HTTPS actif (renouvellement : certbot.timer)"
fi

# ─────────────────────────────────────────────────────────────
step "8. File d'attente et planificateur"
cat > "/etc/systemd/system/$SERVICE.service" <<EOF
[Unit]
Description=File d'attente UAZ (test)
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
echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/uaz-test
chmod 644 /etc/cron.d/uaz-test
ok "$SERVICE actif ; cron du planificateur installé"

# ─────────────────────────────────────────────────────────────
step "9. Tests rapides"
SCHEME=http; grep -q 'ssl_certificate' "$CONF" && SCHEME=https
check() { # check LIBELLÉ ATTENDU URL [options curl]
    local label="$1" want="$2" url="$3"; shift 3
    local code; code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" --resolve "$DOMAIN:80:127.0.0.1" "$@" "$url" || true)"
    if [ "$code" = "$want" ]; then ok "$label → $code"; else warn "$label → $code (attendu $want)"; fi
}
check "/up sans mot de passe" 200 "$SCHEME://$DOMAIN/up" -k
if [ "$BASIC_AUTH" = 1 ]; then
    check "accueil sans mot de passe" 401 "$SCHEME://$DOMAIN/" -k
    [ -z "$BASIC_PASS_SHOWN" ] || check "accueil avec mot de passe" 200 "$SCHEME://$DOMAIN/" -k -u "$BASIC_USER:$BASIC_PASS_SHOWN"
else
    check "accueil public" 200 "$SCHEME://$DOMAIN/" -k
fi
systemctl is-active --quiet "$SERVICE" && ok "queue active" || warn "queue inactive : journalctl -u $SERVICE"

# ─────────────────────────────────────────────────────────────
cat <<EOF

════════════════════════════════════════════════════════════
 Site de test : $SCHEME://$DOMAIN
 Dossier      : $APP_DIR
EOF
if [ "$BASIC_AUTH" != 1 ]; then
    echo ' Accès        : public (X-Robots-Tag noindex + robots.txt fermé)'
elif [ -n "$BASIC_PASS_SHOWN" ]; then
    printf ' Accès Nginx  : %s / %s   (affiché une seule fois)\n' "$BASIC_USER" "$BASIC_PASS_SHOWN"
else
    printf ' Accès Nginx  : utilisateur %s (mot de passe inchangé, voir %s)\n' "$BASIC_USER" "$HTPASSWD"
fi
cat <<EOF
 Secrets      : $SECRETS_DIR (mot de passe base, copie du .env)

 Reste à faire à la main :
   1. Compte administrateur :
        cd $APP_DIR && sudo -u www-data php artisan make:filament-user
        sudo -u www-data php artisan tinker --execute='App\Models\User::first()->assignRole("super-admin");'
   2. Contenu de test (facultatif) : sudo -u www-data php artisan import:wordpress
   3. Journaux : $APP_DIR/storage/logs, /var/log/nginx/error.log, /var/log/$SERVICE.log
════════════════════════════════════════════════════════════
EOF
