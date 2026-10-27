# Déploiement sur un VPS

Cible de référence : **Ubuntu 24.04 LTS**, Nginx, PHP 8.3-FPM, MariaDB 10.11+, HTTPS Let's Encrypt.
Node.js n'est **pas** nécessaire en production : on envoie seulement `public/build/` (résultat de `npm run build`).
Dimensionnement raisonnable : 2 vCPU, 2 Go de RAM, 40 Go de disque (les médias grossissent : prévoir de la marge).

## 1. Préparer le serveur

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mariadb-server git unzip curl ufw fail2ban certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-gd php8.3-intl php8.3-bcmath
# Composer : https://getcomposer.org/download/ (installation globale)

# Pare-feu : SSH, HTTP, HTTPS uniquement
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable

# Utilisateur de déploiement (sans mot de passe SSH, clé uniquement)
sudo adduser --disabled-password --gecos "" deploy && sudo usermod -aG www-data deploy
```

Vérifier que GD sait produire WebP **et** AVIF (sinon seul le WebP sera généré) :
`php -r 'var_dump(function_exists("imagewebp"), function_exists("imageavif"));'`

### PHP-FPM (`/etc/php/8.3/fpm/conf.d/99-uaz.ini`)

```ini
expose_php = Off
memory_limit = 256M
upload_max_filesize = 12M
post_max_size = 16M
date.timezone = Indian/Antananarivo
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   ; relancer php8.3-fpm après chaque déploiement
session.cookie_httponly = 1
session.cookie_samesite = Lax
```

`sudo systemctl reload php8.3-fpm` après modification.

## 2. Base de données

```sql
CREATE DATABASE uaz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'uaz'@'localhost' IDENTIFIED BY '<mot de passe long et unique>';
GRANT ALL PRIVILEGES ON uaz.* TO 'uaz'@'localhost';
```

Exécuter `sudo mysql_secure_installation`. Ne jamais ouvrir le port 3306 sur Internet.

## 3. Installer l'application

```bash
sudo mkdir -p /var/www/uaz && sudo chown deploy:www-data /var/www/uaz
sudo -u deploy git clone <dépôt> /var/www/uaz && cd /var/www/uaz

cp .env.production.example .env          # puis renseigner toutes les valeurs « À DÉFINIR »
composer install --no-dev --optimize-autoloader
php artisan key:generate --force         # une seule fois ; sauvegarder APP_KEY (chiffrement des sessions/2FA)
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=StructureSeeder --force     # réglages, formations, pages système, menus
php artisan storage:link
```

Envoyer le build des assets depuis le poste de développement :
`npm ci && npm run build && rsync -az --delete public/build/ deploy@serveur:/var/www/uaz/public/build/`

Créer le premier compte administrateur (au choix) :
- renseigner `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` dans `.env`, lancer `php artisan db:seed --class=AdminUserSeeder --force`,
  puis **vider ces deux variables** ;
- ou `php artisan make:filament-user` puis `php artisan tinker --execute='App\Models\User::first()->assignRole("super-admin");'`.

Activer ensuite l'authentification à deux facteurs de ce compte (profil → Authentification à deux facteurs).

### Permissions

```bash
sudo chown -R deploy:www-data /var/www/uaz
sudo find /var/www/uaz -type d -exec chmod 755 {} \; && sudo find /var/www/uaz -type f -exec chmod 644 {} \;
sudo chmod -R ug+rwX /var/www/uaz/storage /var/www/uaz/bootstrap/cache
sudo chmod +x /var/www/uaz/scripts/*.sh /var/www/uaz/artisan
sudo chmod 640 /var/www/uaz/.env
```

Seuls `storage/` et `bootstrap/cache/` sont inscriptibles par le serveur web. `.env` n'est jamais servi (le `root` Nginx est `public/`).

## 4. Nginx

`/etc/nginx/sites-available/uaz` (puis `ln -s` vers `sites-enabled`, `nginx -t`, `systemctl reload nginx`) :

```nginx
server {
    listen 80;
    server_name zurcher.edu.mg www.zurcher.edu.mg;
    return 301 https://zurcher.edu.mg$request_uri;      # Certbot complète le bloc HTTPS
}

server {
    listen 443 ssl http2;
    server_name zurcher.edu.mg;
    # ssl_certificate / ssl_certificate_key : ajoutés par Certbot

    root /var/www/uaz/public;
    index index.php;
    charset utf-8;
    client_max_body_size 16m;
    server_tokens off;

    # Compression (connexions lentes) ; activer aussi brotli si le module est installé
    gzip on;
    gzip_comp_level 6;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml text/plain;
    gzip_min_length 1024;

    # Assets Vite (noms hachés) : cache d'un an, immuable
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }

    # Médias envoyés par les rédacteurs (conversions WebP/AVIF comprises)
    location /storage/ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }

    # Favicons, images du sceau
    location ~* \.(?:ico|png|webp|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }

    # Jamais de fichiers cachés (.env, .git…), sauf .well-known (Let's Encrypt)
    location ~ /\.(?!well-known).* { deny all; }
}
```

Les en-têtes de sécurité (CSP à nonce, X-Frame-Options, Referrer-Policy, HSTS…) sont envoyés **par l'application**
(`App\Http\Middleware\SecurityHeaders`) : ne pas les dupliquer dans Nginx.

### Variante Caddy

```caddy
zurcher.edu.mg {
    root * /var/www/uaz/public
    encode zstd gzip
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
    @build path /build/*
    header @build Cache-Control "public, max-age=31536000, immutable"
    @storage path /storage/*
    header @storage Cache-Control "public, max-age=2592000"
    @hidden path_regexp \/\.(?!well-known)
    respond @hidden 404
}
```

Caddy obtient et renouvelle lui-même le certificat HTTPS.

### HTTPS (Nginx)

```bash
sudo certbot --nginx -d zurcher.edu.mg -d www.zurcher.edu.mg --redirect --agree-tos -m <e-mail>
sudo systemctl status certbot.timer       # renouvellement automatique
```

## 5. File d'attente et planificateur

Les conversions d'images et les tâches planifiées (nettoyage des médias orphelins, etc.) en ont besoin.

`/etc/systemd/system/uaz-queue.service` :

```ini
[Unit]
Description=File d'attente UAZ
After=network.target mariadb.service

[Service]
User=deploy
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/uaz
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/uaz-queue.log
StandardError=append:/var/log/uaz-queue.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload && sudo systemctl enable --now uaz-queue
```

Le planificateur Laravel (`crontab -u deploy -e`) :

```cron
* * * * * cd /var/www/uaz && php artisan schedule:run >> /dev/null 2>&1
```

Après chaque déploiement : `php artisan queue:restart` (fait par `scripts/deploy.sh`).

## 6. Optimisation

```bash
php artisan optimize          # config, routes, vues, événements
php artisan filament:optimize
php artisan icons:cache 2>/dev/null || true
```

Pour **régénérer** les images (par exemple pour ajouter l'AVIF après un import) :
`php artisan media-library:regenerate --only-missing`.

## 7. Sauvegardes

Script fourni : `scripts/backup.sh` (base + médias, rotation, copie distante facultative).

```cron
30 2 * * * KEEP_DAYS=14 REMOTE=backup@hote-distant:/sauvegardes/uaz /var/www/uaz/scripts/backup.sh >> /var/log/uaz-backup.log 2>&1
```

- **Conserver au moins une copie hors du serveur** (autre hébergeur, disque externe, stockage objet).
- **Tester la restauration** au moins une fois : `scripts/restore.sh db_….sql.gz media_….tar.gz` sur un serveur de test.
- Sauvegarder aussi le fichier `.env` (surtout `APP_KEY`) dans un coffre de mots de passe, **séparément**.

## 8. Mise à jour courante

```bash
# poste de développement
npm ci && npm run build && rsync -az --delete public/build/ deploy@serveur:/var/www/uaz/public/build/
# serveur
cd /var/www/uaz && scripts/deploy.sh
sudo systemctl reload php8.3-fpm        # vide l'OPcache (validate_timestamps = 0)
```

### Retour arrière

1. `php artisan down` si ce n'est pas déjà fait.
2. `git checkout <commit-précédent>` puis `composer install --no-dev --optimize-autoloader`.
3. Si une migration doit être annulée : `php artisan migrate:rollback --step=1` (seulement si elle a une méthode `down` sûre),
   sinon restaurer la base avec `scripts/restore.sh`.
4. `php artisan optimize:clear && php artisan optimize && php artisan up`.

## 9. Surveillance et maintenance

- Santé : `https://zurcher.edu.mg/up` (réponse 200) — à brancher sur un moniteur externe (UptimeRobot, Better Stack…).
- Journaux : `storage/logs/laravel-AAAA-MM-JJ.log` (30 jours), `/var/log/nginx/error.log`, `/var/log/uaz-queue.log`.
- Mises à jour de sécurité : `unattended-upgrades` activé ; `composer audit` et `npm audit` avant chaque déploiement.
- Disque : surveiller `storage/app/public` (médias) ; `php artisan media-library:clean` supprime les orphelins (planifié chaque nuit).
- Rotation des journaux : `logrotate` pour `/var/log/uaz-*.log`.

## 10. Liste de contrôle avant ouverture

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` en `https://`, `APP_KEY` sauvegardée
- [ ] Mot de passe administrateur fort + **2FA activée** pour chaque compte éditeur/super-admin
- [ ] `SEED_ADMIN_*` vidés ; aucun compte `admin@uaz.test`
- [ ] Contenus de démonstration absents (`php artisan demo:purge`)
- [ ] E-mail du formulaire de contact testé de bout en bout (`CONTACT_RECIPIENT`, SMTP)
- [ ] `robots.txt` ouvert (production) et `sitemap.xml` déclaré dans la Search Console
- [ ] Redirections 301 vérifiées (voir `docs/PLAN-DE-BASCULE.md`)
- [ ] Sauvegarde automatique active **et restauration testée**
- [ ] HTTPS valide, note A sur SSL Labs, en-têtes vérifiés (securityheaders.com)
- [ ] `queue:work` actif (`systemctl status uaz-queue`), cron du planificateur présent

## Vidéo d'en-tête de « À propos »

La page `/a-propos` lit `public/videos/campus-1080.mp4` (écrans larges), `campus-720.mp4` (mobiles, < 768 px) et l'image d'affiche
`campus-poster.webp` (versionnée). Les deux MP4 (≈ 18 Mo et ≈ 6 Mo) ne sont **pas dans Git** : à copier sur le serveur dans
`public/videos/` (`rsync`/SFTP). Absents, la page garde la photo du campus (réglages du site) comme en-tête.
Le script charge la vidéo uniquement si la connexion le permet (pas de mode économie de données, pas de 2G/3G, pas de mouvement réduit).

Fichiers produits à partir du MP4 DJI d'origine (4K HEVC, 40 s, 227 Mo) : 24 premières secondes, redimensionnées (Lanczos), H.264 High,
CRF 25 (1080p) / 26 (720p), sans audio, `+faststart` (lecture avant la fin du téléchargement) :
`ffmpeg -t 24 -i DJI.MP4 -an -vf "scale=1920:-2:flags=lanczos,format=yuv420p" -c:v libx264 -preset slow -crf 25 -maxrate 7M -bufsize 14M -movflags +faststart campus-1080.mp4`

## Qualité des photos de héros
Les images « hero » sont converties en **WebP 2560 px, qualité 90** (sans AVIF : à cette taille, l'AVIF compressé donnait un rendu pâteux).
Après un déploiement ou un changement de ces réglages (`config/uaz.php`, `media.conversions.hero`), régénérer les conversions :
`php artisan media-library:regenerate --only=hero --with-responsive-images --force`.
